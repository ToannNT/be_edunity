<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'section_id',
        'lecture_id',
        'current_time',
        'lecture_title',
        'content',
    ];

    // Quan hệ với User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Quan hệ với Course
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // Quan hệ với Section
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    // Quan hệ với Lecture
    public function lecture()
    {
        return $this->belongsTo(Lecture::class);
    }
}
