<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable implements JWTSubject
{
    const USER_ACTIVE = 'active';
    const USER_INACTIVE = 'inactive';
    const ROLE_STUDENT = 'student';

    const STATUS_DEFAULT = 0;
    const STATUS_DELETED = 1;

    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'avatar',
        'gender',
        'date_of_birth',
        'phone_number',
        'contact_info',
        'biography',
        'address',
        'email_verified',
        'verification_token',
        'role',
        'status',
        'email_verified',
        'stripe_customer_id',
        'reset_token',
        'provider',
        'provider_id'
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'verification_token',
        'reset_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'contact_info' => 'array',
    ];

    // Setter cho contact_info
    public function setAdminContactInfo($value)
    {
        $this->attributes['contact_info'] = json_encode($value);
    }

    // Getter cho contact_info
    public function getAdminContacInfo($value)
    {
        return json_decode($value, true);
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'created_by');
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
