<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'status',
        'description',
        'order',
    ];

    public function course(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lectures()
    {
        return $this->hasMany(Lecture::class, 'section_id');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'section_id');
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

}
