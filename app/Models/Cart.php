<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id'];

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public static function getOrCreateForUser($user)
    {
        return self::firstOrCreate(['user_id' => $user->id]);
    }

    // Lấy danh sách khóa học được định dạng cho response
    public function getFormattedItems()
    {
        return $this->cartItems->map(function ($item) {
            return [
                'id' => $item->course->id,
                'thumbnail' => $item->course->thumbnail,
                'title' => $item->course->title,
                'category_name' => $item->course->category->name,
                'creator' => $item->course->creator
                    ? trim($item->course->creator->last_name . ' ' . $item->course->creator->first_name)
                    : null,
                'old_price' => round($item->course->price),
                'current_price' => $item->current_price,
                'average_rating' => round($item->course->reviews->avg('rating'), 1),
                'reviews_count' => $item->course->reviews->count(),
                'total_duration' => $item->course->total_duration,
                'lectures_count' => $item->course->lectures_count,
                'level' => $item->course->level->name,
            ];
        });
    }

    // Xóa tất cả các mục trong giỏ hàng
    public function clearCart()
    {
        $this->cartItems()->delete();
    }

    // Kiểm tra xem khóa học có tồn tại trong đơn hàng đã thanh toán không
    public function isCourseInPaidOrder($courseId, $userId)
    {
        return Order::where('user_id', $userId)
            ->where('payment_status', 'paid')
            ->whereHas('orderItems', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->exists();
    }

    public function calculateTotalPrice()
    {
        return $this->cartItems->sum('current_price');
    }
}
