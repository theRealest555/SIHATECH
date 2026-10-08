<?php

use App\Http\Controllers\Auth\SocialiteAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// Email links are top-level navigations without the SPA Origin header.
// The web middleware starts the session before Sanctum authenticates the user.
Route::get('/api/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
    ->middleware(['auth:sanctum', 'current.session', 'signed', 'throttle:6,1'])
    ->name('verification.verify')->whereNumber('id');
Route::get('/api/email/verify/error', [VerifyEmailController::class, 'error'])
    ->name('verification.error');

// Health check route
Route::get('/', function () {
    return response()->json([
        'message' => 'Laravel API is running',
        'version' => app()->version(),
        'status' => 'OK',
    ]);
});

Route::middleware('guest')->prefix('api/auth/social')->group(function () {
    Route::get('/{provider}/redirect', [SocialiteAuthController::class, 'redirect'])->name('auth.social.redirect');
    Route::get('/{provider}/callback', [SocialiteAuthController::class, 'callback'])->name('auth.social.callback');
});
