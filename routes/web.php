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
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'aiModels' => \App\Models\AiModel::with(['providers' => function($q) {
                 $q->where('is_primary', true)->with(['schema', 'provider']);
            }])->where('is_active', true)->get()
        ]);
    })->name('dashboard');

    Route::get('/models', [AiModelController::class, 'index'])->name('models.index');
    Route::get('/models/{slug}', [AiModelController::class, 'show'])->name('models.show');
    Route::post('/generate', [GenerationController::class, 'store'])->name('generate');
    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');

    // Apps
    Route::get('/apps', [AppsController::class, 'index'])->name('apps.index');
    Route::get('/apps/luna-influencer', [AppsController::class, 'showLunaInfluencer'])->name('apps.luna-influencer.show');
    Route::post('/apps/luna-influencer/generate', [AppsController::class, 'generateLunaInfluencer'])->name('apps.luna-influencer.generate');

    Route::get('/apps/ai-influencer', [AppsController::class, 'showAiInfluencer'])->name('apps.ai-influencer.show');
    Route::post('/apps/ai-influencer/generate', [AppsController::class, 'generateAiInfluencer'])->name('apps.ai-influencer.generate');
    Route::post('/apps/ai-influencer/generate-video', [AppsController::class, 'generateAiVideo'])->name('apps.ai-influencer.generate-video');
    
    // Video generation routes
    Route::post('/apps/luna-influencer/generate-video', [AppsController::class, 'generateLunaVideo'])->name('apps.luna-influencer.generate-video');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
