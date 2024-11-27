<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Các thuộc tính có thể được gán hàng loạt.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'course_id',
        'price',
        'status',
        'deleted_by',
        'created_by',
        'updated_by',
    ];

    /**
     * Các thuộc tính sẽ được coi là kiểu ngày tháng.
     *
     * @var array<string, string>
     */
    protected $dates = ['deleted_at'];

    /**
     * Định nghĩa mối quan hệ với Order.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Định nghĩa mối quan hệ với Course.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Định nghĩa mối quan hệ với người dùng đã tạo (User).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Định nghĩa mối quan hệ với người dùng đã cập nhật (User).
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Định nghĩa mối quan hệ với người dùng đã xóa (User).
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
