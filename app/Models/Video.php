<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_audio',
        'video_images',
        'video_script',
        'video_transcript',
        'user_id',
    ];

    protected $casts = [
        'video_audio' => 'string',
        'video_images' => 'array',
        'video_script' => 'array',
        'video_transcript' => 'array',
        'user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
