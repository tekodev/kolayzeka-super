<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AiModelController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\AppsController;
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

Route::middleware(['auth', 'verified'])->group(function () {


    Route::get('/models', [AiModelController::class, 'index'])->name('models.index');
    Route::get('/models/{slug}', [AiModelController::class, 'show'])->name('models.show');
    Route::get('/models/{slug}/docs', [AiModelController::class, 'docs'])->name('models.docs');
    Route::post('/generate', [GenerationController::class, 'store'])->name('generate');
    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');

    // Apps
    Route::get('/apps', [AppsController::class, 'index'])->name('apps.index');
    Route::get('/apps/luna-influencer', [AppsController::class, 'showLunaInfluencer'])->name('apps.luna-influencer.show');
    Route::post('/apps/luna-influencer/generate', [AppsController::class, 'generateLunaInfluencer'])->name('apps.luna-influencer.generate');

    Route::get('/apps/download/{generation}', [AppsController::class, 'download'])->name('apps.download');

    Route::get('/apps/ai-influencer', [AppsController::class, 'showAiInfluencer'])->name('apps.ai-influencer.show');
    Route::post('/apps/ai-influencer/generate', [AppsController::class, 'generateAiInfluencer'])->name('apps.ai-influencer.generate');
    Route::post('/apps/ai-influencer/generate-video', [AppsController::class, 'generateAiVideo'])->name('apps.ai-influencer.generate-video');
    
    // Video generation routes
    Route::post('/apps/luna-influencer/generate-video', [AppsController::class, 'generateLunaVideo'])->name('apps.luna-influencer.generate-video');

    // Dynamic Apps
    Route::post('/apps/execution/{execution}/approve', [AppsController::class, 'approve'])->name('apps.execution.approve');
    Route::get('/apps/execution/{execution}', [AppsController::class, 'executionStatus'])->name('apps.execution.status');
    Route::post('/apps/{slug}/execute', [AppsController::class, 'execute'])->name('apps.execute');
    Route::get('/apps/{slug}', [AppsController::class, 'show'])->name('apps.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
