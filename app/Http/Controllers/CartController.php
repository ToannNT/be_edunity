<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class CartController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();
            $cart = Cart::getOrCreateForUser($user);
            $courses = $cart->getFormattedItems();

            return $this->formatResponse('success', __('messages.cart_items_fetched'), $courses);
        } catch (\Exception $e) {
            return $this->formatResponse('error', $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'course_id' => 'required|exists:courses,id',
            ]);

            $user = Auth::user();
            $cart = Cart::getOrCreateForUser($user);

            if ($cart->isCourseInPaidOrder($validatedData['course_id'], $user->id)) {
                return $this->formatResponse('error', __('messages.course_already_in_paid_order'), null, 400);
            }

            DB::beginTransaction();
            $existingCartItem = $cart->cartItems()->where('course_id', $validatedData['course_id'])->first();
            if ($existingCartItem) {
                throw new \Exception(__('messages.course_already_in_cart'), 400);
            }

            $cart->cartItems()->create([
                'course_id' => $validatedData['course_id'],
            ]);

            DB::commit();

            $courses = $cart->getFormattedItems();
            return $this->formatResponse('success', __('messages.course_added_success'), $courses, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->formatResponse('error', 'Validation Error', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->formatResponse('error', $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    public function destroy($course_id)
    {
        try {
            $user = Auth::user();
            $cart = Cart::getOrCreateForUser($user);
            $cartItem = $cart->cartItems()->where('course_id', $course_id)->first();

            if (!$cartItem) {
                return $this->formatResponse('error', __('messages.course_not_found_in_cart'), null, 404);
            }

            $cartItem->delete();
            $courses = $cart->getFormattedItems();
            return $this->formatResponse('success', __('messages.course_removed_success'), $courses, 204);
        } catch (\Exception $e) {
            return $this->formatResponse('error', $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    public function destroyAll()
    {
        try {
            $user = Auth::user();
            $cart = Cart::getOrCreateForUser($user);
            $cart->clearCart();

            return $this->formatResponse('success', __('messages.cart_cleared'), null, 204);
        } catch (\Exception $e) {
            return $this->formatResponse('error', $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    public function applyVoucher(Request $request)
    {
        try {
            $user = Auth::user();
            $cart = Cart::getOrCreateForUser($user);

            $data = $request->validate([
                'voucher_code' => 'required|string|exists:vouchers,code',
            ]);

            $voucher = Voucher::where('code', $data['voucher_code'])->firstOrFail();

            $hasUsedVoucher = Order::where('user_id', $user->id)
                ->where('voucher_id', $voucher->id)
                ->where('payment_status', 'paid')
                ->exists();

            if ($hasUsedVoucher) {
                return $this->formatResponse('error', __('messages.voucher_already_used'), null, 400);
            }

            $totalPrice = $cart->calculateTotalPrice();
            $result = $voucher->apply($totalPrice, $user->id);

            return $this->formatResponse('success', __('messages.voucher_applied_successfully'), [
                'total_price' => $totalPrice,
                'discount' => $result['discount'],
                'total_price_after_discount' => $result['total_price_after_discount'],
            ]);
        } catch (\Exception $e) {
            return $this->formatResponse('error', 'Error: ' . $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    private function formatResponse($status, $message, $data = null, $code = 200)
    {
        $response = [
            'status' => $status,
            'message' => $message,
        ];

        if ($data) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }
}
