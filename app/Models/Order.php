<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Các thuộc tính có thể được gán hàng loạt.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'voucher_id',
        'order_code',
        'total_price',
        'currency',
        'payment_method',
        'payment_status',
        'payment_code',
        'status',
        'deleted_by',
        'created_by',
        'updated_by',
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Các thuộc tính sẽ được coi là kiểu ngày tháng.
     *
     * @var array<string, string>
     */
    protected $dates = ['deleted_at'];

    /**
     * Định nghĩa quan hệ với người dùng (User).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Định nghĩa quan hệ với mã giảm giá (Voucher).
     */
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Định nghĩa quan hệ với người dùng đã tạo (User) thông qua created_by.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Định nghĩa quan hệ với người dùng đã cập nhật (User) thông qua updated_by.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Định nghĩa quan hệ với người dùng đã xóa (User) thông qua deleted_by.
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
