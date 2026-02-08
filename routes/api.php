<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

Route::get('/ai-models', [\App\Http\Controllers\Api\AiModelController::class, 'index']);
Route::get('/ai-models/{slug}', [\App\Http\Controllers\Api\AiModelController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/models/{slug}/generate', [\App\Http\Controllers\Api\GenerationController::class, 'generate']);
    Route::get('/generations', [\App\Http\Controllers\Api\GenerationController::class, 'index']);
});
