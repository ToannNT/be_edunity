<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressQuiz extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'progress_quizzes';

    // Các cột có thể được điền giá trị
    protected $fillable = [
        'user_id',
        'quiz_id',
        'questions_done',
        'percent',
        'status',
        'deleted_by',
        'created_by',
        'updated_by',
    ];

    /**
     * Quan hệ tới bảng `users`
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Quan hệ tới bảng `quizzes`
     */
    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    /**
     * Scope để lấy progress active
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope để lấy progress của một người dùng cụ thể
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope để lấy progress của một quiz cụ thể
     */
    public function scopeForQuiz($query, $quizId)
    {
        return $query->where('quiz_id', $quizId);
    }
}
