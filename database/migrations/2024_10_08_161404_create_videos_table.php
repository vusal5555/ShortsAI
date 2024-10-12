<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('video_audio'); // Store the path or filename for the audio file.
            $table->json('video_images'); // Store an array of image paths/URLs in JSON format.
            $table->json('video_script'); // Store an array of script lines or data in JSON format.
            $table->json('video_transcript'); // Store an array of transcripts in JSON format.
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
