<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Stripe\OAuth;
use Stripe\Stripe;

class PaymentMethodController extends Controller
{
    public function linkStripe()
    {
        $user = Auth::user();

//        // Kiểm tra xem người dùng đã có phương thức Stripe chưa
//        $existingStripe = PaymentMethod::where('user_id', $user->id)
//            ->where('type', 'stripe')
//            ->where('status', 'active')
//            ->first();
//
//        if ($existingStripe) {
//            return formatResponse(STATUS_FAIL, '', '', __('messages.stripe_already_linked'));
//        }

        // Tạo payload cho JWT
        $payload = [
            'user_id' => $user->id,
            'exp' => now()->addMinutes(10)->timestamp, // Hạn sử dụng 10 phút
        ];
        $jwt = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        $stripeClientId = env('STRIPE_CLIENT_ID');
        $redirectUri = env('URL_BASE_PUBLIC_BE') . "/api/auth/payment-methods/stripe/callback";
        $url = "https://connect.stripe.com/oauth/authorize?response_type=code&client_id={$stripeClientId}&scope=read_write&redirect_uri={$redirectUri}&state={$jwt}";
        return formatResponse(STATUS_OK, ['url' => $url], '', __('messages.stripe_link_url'));
    }

    /**
     * Xử lý callback từ Stripe sau khi liên kết.
     */
    public function handleStripeCallback(Request $request)
    {
        $code = $request->input('code');
        $state = $request->input('state');

        if (!$code || !$state) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.stripe_invalid_code'));
        }

        try {
            $decoded = JWT::decode($state, new Key(env('JWT_SECRET'), 'HS256'));
            $user = User::find($decoded->user_id);
            if (!$user) {
                return formatResponse(STATUS_FAIL, '', '', 'user_not_found');
            }
            if ($decoded->exp < now()->timestamp) {
                return formatResponse(STATUS_FAIL, '', '', 'stripe_state_expired');
            }
        } catch (\Exception $e) {
            return formatResponse(STATUS_FAIL, '', $e->getMessage(), 'stripe_invalid_state');
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Trao đổi code lấy access token
            $response = OAuth::token([
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);
            $existingStripe = PaymentMethod::where('user_id', $user->id)
                ->where('type', 'stripe')
                ->where('status', 'active')
                ->first();
            if ($existingStripe) {
                return formatResponse(STATUS_FAIL, '', '', __('messages.stripe_already_linked'));
            }

            PaymentMethod::create([
                'user_id' => $user->id,
                'type' => 'stripe',
                'details' => [
                    'stripe_account_id' => $response->stripe_user_id,
                    'scope' => $response->scope,
                    'token_type' => $response->token_type,
                    'refresh_token' => $response->refresh_token,
                ],
                'account_info_number' => $response->stripe_user_id,
                'status' => 'active',
            ]);
            return formatResponse(STATUS_OK, '', '', 'Stripe account linked successfully.');
        } catch (\Exception $e) {
            return formatResponse(STATUS_FAIL, '', $e->getMessage(), __('messages.stripe_link_fail'));
        }
    }

    /**
     * Liệt kê các phương thức thanh toán của user.
     */
    public function listPaymentMethods()
    {
        $user = Auth::user();
        $paymentMethods = PaymentMethod::where('user_id', $user->id)->get();
        return formatResponse(STATUS_OK, $paymentMethods, '', __('messages.payment_methods_list'));
    }

    /**
     * Thêm các phương thức thanh toán khác (ví dụ: PayPal, Bank Transfer, ...)
     */
    public function addPaymentMethod(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:paypal,bank_transfer,momo,vnpay',
            'details' => 'required|array',
        ], [
            'type.required' => __('messages.payment_method_type_required'),
            'type.in' => __('messages.payment_method_type_invalid'),
            'details.required' => __('messages.payment_method_details_required'),
            'details.array' => __('messages.payment_method_details_invalid'),
        ]);
        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }
        $type = $request->input('type');
        $details = $request->input('details');
        $existingMethod = PaymentMethod::where('user_id', $user->id)->where('type', $type)->first();
        if ($existingMethod) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.payment_method_already_exists'));
        }
        $paymentMethod = PaymentMethod::create([
            'user_id' => $user->id,
            'type' => $type,
            'details' => $details,
            'status' => 'active',
        ]);
        return formatResponse(STATUS_OK, $paymentMethod, '', __('messages.payment_method_added'));
    }

    /**
     * Xóa phương thức thanh toán.
     */
    public function deletePaymentMethod($id)
    {
        $user = Auth::user();
        $paymentMethod = PaymentMethod::where('user_id', $user->id)->where('id', $id)->first();
        if (!$paymentMethod) {
            return formatResponse(STATUS_FAIL, '', '', __('messages.payment_method_not_found'));
        }
        $paymentMethod->delete();
        return formatResponse(STATUS_OK, '', '', __('messages.payment_method_deleted'));
    }
}
