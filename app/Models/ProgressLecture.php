<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressLecture extends Model
{
    use SoftDeletes;

    // Table name
    protected $table = 'progress_lectures';

    // Fillable fields for mass assignment
    protected $fillable = [
        'user_id',
        'lecture_id',
        'learned',
        'percent',
        'status',
        'deleted_by',
        'created_by',
        'updated_by',
    ];


    // Timestamps
    public $timestamps = true;

    // Relationships

    /**
     * Get the user associated with the progress.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the lecture associated with the progress.
     */
    public function lecture()
    {
        return $this->belongsTo(Lecture::class);
    }

    /**
     * Get the course associated with the progress (if needed).
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
