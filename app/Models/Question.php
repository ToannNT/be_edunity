<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'questions';

    protected $fillable = [
        'quiz_id',
        'question',
        'options',
        'answer',
        'status',
        'order',
        'deleted_by',
        'created_by',
        'updated_by',
    ];

    /**
     * Cast options to array automatically
     */
    protected $casts = [
        'options' => 'array', // Chuyển JSON thành mảng khi truy xuất
    ];

    /**
     * Relationships
     */

    // Một câu hỏi thuộc về một quiz
    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    /**
     * Scope for active questions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

