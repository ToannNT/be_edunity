<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'details',
        'status',
        'account_info_number'
    ];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Quan hệ với User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
