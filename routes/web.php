<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VideoController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('/dashboard');

    Route::get('/create-new', [VideoController::class, 'index'])->name('/create-new');

    Route::post('/generate-video-script', [VideoController::class, 'generateVideoScript'])->name('/generate-video-script');
    Route::post('/generate-audio-transcript', [VideoController::class, 'generateAudioAndTranscript'])->name('/generate-audio-transcript');
    Route::post('/generate-images', [VideoController::class, 'generateImages'])->name('/generate-images');

    Route::post('/generate-video', [VideoController::class, 'generateVideo'])->name('/generate-video');
    Route::get('/get-video', [VideoController::class, 'getVideo'])->name('/get-video');
    Route::get('/get-all-videos', [VideoController::class, 'getAllVideos'])->name('/get-all-videos');
});

require __DIR__ . '/auth.php';
