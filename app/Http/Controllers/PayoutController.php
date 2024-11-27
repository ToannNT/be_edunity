<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\ApiErrorException;
use Stripe\Payout;
use Stripe\Stripe;
use Stripe\Webhook;

class PayoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api',
            [
                'except' => [
                    'handleWebhook',
                ]
            ]);
    }

    public function requestPayout(Request $request)
    {

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

//        $balance = \Stripe\Balance::retrieve();
        $charge = \Stripe\Charge::create([
            'amount' => 1000, // 10 USD (tính bằng cents)
            'currency' => 'usd',
            'source' => 'tok_visa', // Token test
            'description' => 'Test charge to increase balance',
        ]);

        // Chuyển tiền từ platform account đến connected account
        $transfer = \Stripe\Transfer::create([
            'amount' => 1000, // 10 USD
            'currency' => 'usd',
            'destination' => 'acct_1QOXVvCzrOkmIPq4', // ID của connected account
            'description' => 'Test transfer to connected account',
        ]);

        $user = Auth::user();
        // Kiểm tra dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10',
            'currency' => 'required|string|in:usd,eur,vnd',
        ], [
            'amount.required' => __('messages.amount_required'),
            'amount.numeric' => __('messages.amount_numeric'),
            'amount.min' => __('messages.amount_min'),
            'currency.required' => __('messages.currency_required'),
            'currency.in' => __('messages.currency_invalid'),
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'), 400);
        }

        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $reason = $request->input('reason');
        // Kiểm tra số dư khả dụng của user
        $availableBalance = $this->calculateAvailableBalance($user->id);

        if ($amount > $availableBalance) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.amount_exceeds_balance'), 400);
        }

        // Lấy phương thức thanh toán mặc định (Stripe nếu có)
        $paymentMethod = PaymentMethod::where('user_id', $user->id)->where('status', 'active')->first();

        if (!$paymentMethod) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.payment_method_not_found'), 400);
        }
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'reason' => $reason ?? null,
        ]);
        return formatResponse(STATUS_OK, $payoutRequest, '', __('messages.payout_request_created'), 200);
    }

    private function calculateAvailableBalance($userId)
    {
//        var_dump($userId); die;

        //Tính từ tổng doanh thu đã bán - đã rút
        $totalRevenue = \App\Models\Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('courses', 'order_items.course_id', '=', 'courses.id')
            ->where('courses.created_by', $userId)
            ->where('orders.payment_status', 'paid')
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->sum('order_items.price');


        // phần trăm giáo viên 70%
        $sharePercentage = 70;
        $availableBalance = ($totalRevenue * $sharePercentage) / 100;


        // Trừ đi các yêu cầu rút tiền đã được duyệt nhưng chưa hoàn thành
        $pendingPayouts = PayoutRequest::where('user_id', $userId)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');
        return $availableBalance - $pendingPayouts;
    }

    //admin xử lý
    public function processPayout(Request $request, $id)
    {
//        $admin = Auth::user();
        // Tìm yêu cầu rút tiền
        $payoutRequest = PayoutRequest::find($id);
        if (!$payoutRequest) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.payout_request_not_found'), 404);
        }

        if ($payoutRequest->status !== 'pending') {
            return formatResponse(STATUS_FAIL, '', '', __('messages.payout_request_not_pending'), 400);
        }

        // Lấy phương thức thanh toán của user (Stripe nếu có)
        $paymentMethod = PaymentMethod::where('user_id', $payoutRequest->user_id)
            ->where('type', 'stripe')
            ->where('status', 'active')
            ->first();

        if (!$paymentMethod) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.stripe_not_linked'), 400);
        }

        // Thực hiện payout qua Stripe
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Tạo payout trên Stripe
            $stripePayout = Payout::create([
                'amount' => $payoutRequest->amount * 100, // Stripe yêu cầu tính bằng cents
                'currency' => $payoutRequest->currency,
                'description' => 'Payout for user ID: ' . $payoutRequest->user_id,
                'metadata' => [
                    'payout_request_id' => $payoutRequest->id,
                ],
            ], [
                'stripe_account' => $paymentMethod->details['stripe_account_id'],
            ]);

            // Cập nhật yêu cầu rút tiền với thông tin Stripe
            $payoutRequest->update([
                'status' => 'processing',
                'reason' => null, // Reset lý do nếu có
            ]);

            return formatResponse(STATUS_OK, $payoutRequest, '', __('messages.payout_processing'), 200);
        } catch (ApiErrorException $e) {
            // Cập nhật trạng thái yêu cầu rút tiền nếu có lỗi
            $payoutRequest->update([
                'status' => 'failed',
                'reason' => $e->getMessage(),
            ]);

            return formatResponse(STATUS_FAIL, '', 'Stripe Error: ' . $e->getMessage(), __('messages.payout_failed'), 500);
        }
    }

    //admin
    public function listPayoutRequests(Request $request)
    {
        $admin = Auth::user();
        // Lấy các yêu cầu rút tiền với phân trang
        $payoutRequests = PayoutRequest::with('user', 'user.paymentMethods')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return formatResponse(STATUS_OK, $payoutRequests, '', __('messages.payout_requests_list'), 200);
    }


    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Xử lý sự kiện
        if ($event->type === 'payout.paid' || $event->type === 'payout.failed') {
            $payout = $event->data->object; // StripePayout object

            // Lấy payout_request_id từ metadata
            $payoutRequestId = $payout->metadata->payout_request_id ?? null;

            if ($payoutRequestId) {
                $payoutRequest = PayoutRequest::find($payoutRequestId);

                if ($payoutRequest) {
                    if ($event->type === 'payout.paid') {
                        $payoutRequest->update([
                            'status' => 'completed',
                            'reason' => null,
                        ]);
                    } elseif ($event->type === 'payout.failed') {
                        $payoutRequest->update([
                            'status' => 'failed',
                            'reason' => $payout->failure_code ?? 'Unknown error',
                        ]);
                    }
                } else {
                    Log::warning('PayoutRequest not found for payout_request_id: ' . $payoutRequestId);
                }
            } else {
                Log::warning('payout_request_id not found in payout metadata.');
            }
        }

        // Xử lý các sự kiện khác nếu cần

        return response()->json(['message' => 'Webhook handled'], 200);
    }
}
