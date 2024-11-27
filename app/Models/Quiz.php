<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quiz extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quizzes';

    protected $fillable = [
        'section_id',
        'title',
        'status',
        'order',
        'deleted_by',
        'created_by',
        'updated_by',
    ];

    /**
     * Relationships
     */

    // Một quiz có nhiều câu hỏi
    public function questions()
    {
        return $this->hasMany(Question::class, 'quiz_id');
    }

    /**
     * Scope for active quizzes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    public function progress()
    {
        return $this->hasMany(ProgressQuiz::class, 'quiz_id');
    }
}
