<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnswerUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'answers_users';

    protected $fillable = [
        'user_id',
        'question_id',
        'content',
        'status',
        'deleted_by',
        'created_by',
        'updated_by',
    ];

    // Quan hệ với bảng User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Quan hệ với bảng Question
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
