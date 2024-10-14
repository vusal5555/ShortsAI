<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'videoAudio',
        'videoImages',
        'videoScript',
        'videoTranscript',
        'user_id',
    ];

    protected $casts = [
        'videoImages' => 'array',
        'videoScript' => 'array',
        'videoTranscript' => 'array',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
