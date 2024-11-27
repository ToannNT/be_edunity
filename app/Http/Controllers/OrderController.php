<?php

namespace App\Http\Controllers;

use App\Models\{Cart, CartItem, Order, OrderItem, Voucher};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // đơn hàng mới nhất
        $orders = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->first();
        $cartItems = CartItem::where('cart_id', $cart->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Cart is empty'], 400);
        }

        // Tính tổng giá trị đơn hàng
        $total = round($cartItems->reduce(function ($carry, $item) {
            $price = $item->course->type_sale === 'percent'
                ? $item->course->price - ($item->course->price * $item->course->sale_value / 100)
                : $item->course->price - $item->course->sale_value;
            return $carry + $price;
        }, 0));

        $currency = $request->currency ?? 'vnd';
        $total = ($currency == 'usd') ? $total * 100 : $total;

        $voucher = Voucher::where('code', $request->voucher)->first();

        if ($voucher) {
            // Kiểm tra nếu voucher đã được người dùng sử dụng
            $hasUsedVoucher = Order::where('user_id', $user->id)
                ->where('voucher_id', $voucher->id)
                ->where('payment_status', 'paid')
                ->exists();

            if ($hasUsedVoucher) {
                return response()->json(['status' => 'error', 'message' => 'Voucher has already been used by the user.'], 400);
            }

            // Tính toán mức giảm giá
            $discount = $voucher->discount_type === 'percent'
                ? min($total * $voucher->discount_value / 100, $voucher->max_discount_value ?? PHP_INT_MAX)
                : min($voucher->discount_value, $voucher->max_discount_value ?? PHP_INT_MAX);

            $total = max($total - $discount, 0);
            $total = round($total);
        }

        try {
            DB::beginTransaction();

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            if (!$user->email) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('message.order_require_email'),
                ], 400);
            }

            $response = $stripe->checkout->sessions->create([
                'customer_email' => $user->email,
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => ['name' => 'Edunity Courses'],
                            'unit_amount' => $total,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => config('services.frontend_url') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('services.frontend_url') . '/checkout/cancel',
            ]);

            // Tạo đơn hàng trong hệ thống
            $order = Order::create([
                'user_id' => $user->id,
                'voucher_id' => $voucher ? $voucher->id : null,
                'order_code' => 'Edunity#' . Str::random(10),
                'total_price' => $total,
                'currency' => $currency,
                'payment_method' => 'Stripe',
                'payment_status' => 'pending',
                'payment_code' => $response->id,
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'course_id' => $item->course_id,
                    'price' => $item->course->type_sale === 'percent'
                        ? $item->course->price - ($item->course->price * $item->course->sale_value / 100)
                        : $item->course->price - $item->course->sale_value,
                ]);
            }

            $cartItems->each->delete();
            $cart->delete();

            // Tăng số lần sử dụng của voucher khi tạo đơn hàng thành công
            if ($voucher) {
                $voucher->increment('usage_count');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'checkout_url' => $response->url,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Order creation failed: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->with('orderItems')
            ->first();

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $order]);
    }

    public function cancel(Request $request, $id)
    {
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$order || $order->payment_status === 'paid') {
            return response()->json(['status' => 'error', 'message' => 'Cannot cancel this order'], 400);
        }

        DB::transaction(function () use ($order) {
            // Khôi phục voucher (tăng số lần sử dụng trở lại)
            if ($order->voucher_id) {
                $voucher = Voucher::find($order->voucher_id);
                $voucher->decrement('usage_count');
            }

            // Khôi phục số tiền chưa áp dụng mã giảm giá
            if ($order->voucher_id) {
                $originalTotalPrice = $order->total_price;
                $voucher = Voucher::find($order->voucher_id);
                $discount = $voucher->discount_type === 'percent'
                    ? min($originalTotalPrice * $voucher->discount_value / 100, $voucher->max_discount_value ?? PHP_INT_MAX)
                    : min($voucher->discount_value, $voucher->max_discount_value ?? PHP_INT_MAX);
                $order->update(['total_price' => $originalTotalPrice + $discount]);
            }

            // Hủy đơn hàng
            $order->update(['payment_status' => 'cancelled']);
        });

        return response()->json(['status' => 'success', 'message' => 'Order cancelled successfully']);
    }

    public function restore(Request $request, $id)
    {
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        // Kiểm tra xem đơn hàng có thể được khôi phục hay không
        if (!$order || $order->payment_status !== 'cancelled') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only cancelled orders can be restored',
            ], 400);
        }

        // Đặt trạng thái đơn hàng thành 'pending'
        $order->update(['payment_status' => 'pending']);

        try {
            // Tạo phiên thanh toán Stripe mới
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            if (!$user->email) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('message.order_require_email'),
                ], 400);
            }

            $response = $stripe->checkout->sessions->create([
                'customer_email' => $user->email,
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $order->currency ?? 'vnd',
                            'product_data' => ['name' => "Edunity Courses"],
                            'unit_amount' => $order->total_price,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => config('services.frontend_url') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('services.frontend_url') . '/checkout/cancel',
            ]);

            // Cập nhật mã phiên thanh toán mới trong đơn hàng
            $order->update(['payment_code' => $response->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Order restored successfully. Proceed to payment.',
                'checkout_url' => $response->url,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create a new checkout session: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create a new checkout session',
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return response()->json(['status' => 'error', 'message' => 'Session ID is required'], 400);
        }

        $order = Order::where('payment_code', $sessionId)->first();

        if ($order && $order->payment_status === 'paid') {
            return response()->json(['status' => 'success', 'payment_status' => $order->payment_status]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Payment not completed'], 400);
        }
    }

    public function handleWebhook(Request $request)
    {
        $endpoint_secret = config('services.stripe.webhook_secret');
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);

            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;
                $order = Order::where('payment_code', $session->id)->first();

                if ($order && $order->payment_status === 'pending') {
                    $order->update(['payment_status' => 'paid']);
                    Log::info("Order #{$order->id} has been marked as paid.");
                } elseif (!$order) {
                    Log::warning("Order not found for session ID: {$session->id}");
                }
            } elseif ($event->type === 'checkout.session.expired') {
                $session = $event->data->object;
                $order = Order::where('payment_code', $session->id)->first();

                if ($order && $order->payment_status === 'pending') {
                    $order->update(['payment_status' => 'cancelled']);
                    Log::info("Order #{$order->id} has been cancelled due to expired session.");
                } elseif (!$order) {
                    Log::warning("Order not found for session ID: {$session->id}");
                }
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid payload');
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid signature');
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
        }
    }
}
