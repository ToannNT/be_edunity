<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'usage_limit',
        'usage_count',
        'expires_at',
        'min_order_value',
        'max_discount_value',
        'status',
        'created_by',
        'deleted_by',
        'updated_by'
    ];

    protected $dates = ['expires_at', 'deleted_at'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public static function createNewVoucher($data)
    {
        return self::create($data);
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function apply($totalPrice, $userId)
    {
        if ($this->status !== 'active' || $this->isExpired()) {
            throw new \Exception(__('messages.invalid_or_expired_voucher'));
        }

        $hasUsedVoucher = Order::where('user_id', $userId)
            ->where('voucher_id', $this->id)
            ->where('payment_status', 'paid')
            ->exists();

        if ($hasUsedVoucher) {
            throw new \Exception(__('messages.voucher_already_used'));
        }

        $discountAmount = $this->discount_type === 'percent'
            ? ($totalPrice * $this->discount_value) / 100
            : $this->discount_value;

        $discountAmount = $this->max_discount_value
            ? min($discountAmount, $this->max_discount_value)
            : $discountAmount;

        return [
            'discount' => $discountAmount,
            'total_price_after_discount' => max(0, $totalPrice - $discountAmount),
        ];
    }

    public static function softDeleteByCode($code)
    {
        $voucher = self::where('code', $code)->firstOrFail();
        $voucher->deleted_by = Auth::id();
        $voucher->save();
        $voucher->delete();
    }

    public static function restoreByCode($code)
    {
        $voucher = self::withTrashed()->where('code', $code)->firstOrFail();
        $voucher->restore();
        return $voucher;
    }

    public static function getAllDeleted()
    {
        return self::onlyTrashed()->get();
    }

    public function updateVoucher($data)
    {
        $this->update(array_filter($data, fn($value) => !is_null($value)));
    }
}
