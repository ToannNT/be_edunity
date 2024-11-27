<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'cart_id'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // Lấy giá hiện tại của khóa học từ Course model
    public function getCurrentPriceAttribute()
    {
        $course = $this->course;
        return $course->type_sale === 'percent'
            ? round($course->price - ($course->price * $course->sale_value / 100))
            : round($course->price - $course->sale_value);
    }
}
