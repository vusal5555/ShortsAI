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
            $table->text('videoAudio'); // Store the path or filename for the audio file.
            $table->json('videoImages'); // Store an array of image paths/URLs in JSON format.
            $table->json('videoScript'); // Store an array of script lines or data in JSON format.
            $table->json('videoTranscript'); // Store an array of transcripts in JSON format.
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
