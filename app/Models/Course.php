<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'level_id',
        'title',
        'short_description',
        'description',
        'thumbnail',
        'price',
        'type_sale',
        'sale_value',
        'language_id',
        'status',
        'created_by',
        'updated_by',
    ];


    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function level()
    {
        return $this->belongsTo(CourseLevel::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by'); // Sử dụng created_by làm khóa ngoại
    }
    public function language()
    {
        return $this->belongsTo(Language::class, 'language_id');
    }

    public function averageRating()
    {
        return $this->reviews()->avg('rating');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'course_id');
    }

    public function sections()
    {
        return $this->hasMany(Section::class, 'course_id');
    }
    public function getTotalDurationAttribute()
    {
        return $this->sections->sum(function ($section) {
            return $section->lectures->sum('duration');
        });
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'course_id');
    }



}
