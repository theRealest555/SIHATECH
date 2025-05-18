<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\SocialiteAuthController;
use App\Http\Controllers\Patient\ProfileController as PatientProfileController;
use App\Http\Controllers\Doctor\ProfileController as DoctorProfileController;
use App\Http\Controllers\Doctor\DocumentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DoctorVerificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// Email verification routes
Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/email/verify/error', [VerifyEmailController::class, 'error'])
    ->name('verification.error');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');

// Check verification status
Route::get('/email/verify/check', function (Request $request) {
    return response()->json([
        'verified' => $request->user() && $request->user()->hasVerifiedEmail(),
    ]);
})->middleware(['auth:sanctum']);

// Social authentication routes
Route::prefix('auth')->group(function () {
    // Redirect to social provider
    Route::get('/social/{provider}/redirect', [SocialiteAuthController::class, 'redirect'])
        ->name('auth.social.redirect');
    
    // Handle provider callback
    Route::get('/social/{provider}/callback', [SocialiteAuthController::class, 'callback'])
        ->name('auth.social.callback');
});

// Doctor profile completion route (requires doctor token)
Route::middleware(['auth:sanctum', 'abilities:medecin'])->group(function () {
    Route::post('/doctor/complete-profile', [DoctorProfileController::class, 'completeProfile']);
});

// Protected routes
Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
    // Common routes for all authenticated users
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    
    // Routes that require email verification
    Route::middleware(['verified'])->group(function () {
        // Patient routes
        Route::middleware('role:patient')->prefix('patient')->group(function () {
            Route::get('/profile', [PatientProfileController::class, 'show']);
            Route::put('/profile', [PatientProfileController::class, 'update']);
            Route::put('/profile/password', [PatientProfileController::class, 'updatePassword']);
            Route::post('/profile/photo', [PatientProfileController::class, 'updatePhoto']);
        });
        
        // Doctor routes
        Route::middleware('role:medecin')->prefix('doctor')->group(function () {
            // Profile management
            Route::get('/profile', [DoctorProfileController::class, 'show']);
            Route::put('/profile', [DoctorProfileController::class, 'update']);
            Route::put('/profile/password', [DoctorProfileController::class, 'updatePassword']);
            Route::post('/profile/photo', [DoctorProfileController::class, 'updatePhoto']);
            
            // Document management
            Route::get('/documents', [DocumentController::class, 'index']);
            Route::post('/documents', [DocumentController::class, 'store']);
            Route::get('/documents/{id}', [DocumentController::class, 'show']);
            Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
            
            // Routes that require verified doctor status
            Route::middleware('verified.doctor')->group(function () {
                // Add routes for verified doctors here (like appointment management)
            });
        });
    });
    
    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Admin auth
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        
        // User management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/admin', [UserController::class, 'storeAdmin']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
        Route::put('/users/{id}/password', [UserController::class, 'resetPassword']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        
        // Doctor verification
        Route::get('/doctors/pending', [DoctorVerificationController::class, 'pendingDoctors']);
        Route::get('/documents/pending', [DoctorVerificationController::class, 'pendingDocuments']);
        Route::get('/documents/{id}', [DoctorVerificationController::class, 'showDocument']);
        Route::post('/documents/{id}/approve', [DoctorVerificationController::class, 'approveDocument']);
        Route::post('/documents/{id}/reject', [DoctorVerificationController::class, 'rejectDocument']);
        Route::post('/doctors/{id}/verify', [DoctorVerificationController::class, 'verifyDoctor']);
        Route::post('/doctors/{id}/revoke', [DoctorVerificationController::class, 'revokeVerification']);
    });
});