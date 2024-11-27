<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lecture extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'title',
        'content_link',
        'link_url',
        'status',
        'duration',
        'order',
    ];

    public function section(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
    public function progress()
    {
        return $this->hasMany(ProgressLecture::class, 'lecture_id');
    }
    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
