<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// ─── API Auth Endpoints (session-based) ───────────────────────

Route::prefix('api/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// ─── OAuth Redirects & Callbacks (web routes for browser flow) ─

Route::prefix('auth')->group(function () {
    // GitHub
    Route::get('/github/redirect', [AuthController::class, 'githubRedirect'])
        ->name('auth.github.redirect');
    Route::get('/github/callback', [AuthController::class, 'githubCallback'])
        ->name('auth.github.callback');

    // Apple (uses form_post response mode)
    Route::get('/apple/redirect', [AuthController::class, 'appleRedirect'])
        ->name('auth.apple.redirect');
    Route::post('/apple/callback', [AuthController::class, 'appleCallback'])
        ->name('auth.apple.callback');
});
