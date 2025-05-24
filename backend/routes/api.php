<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\SocialiteAuthController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Patient\ProfileController as PatientProfileController;
use App\Http\Controllers\Doctor\ProfileController as DoctorProfileController;
use App\Http\Controllers\Doctor\DocumentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DoctorVerificationController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\DoctorController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Guest Routes (No Authentication Required)
Route::middleware('guest')->group(function () {
    // Authentication Routes
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/admin/login', [AdminAuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
    Route::post('/reset-password', [NewPasswordController::class, 'store']);

    // Social Authentication
    Route::get('/auth/social/{provider}/redirect', [SocialiteAuthController::class, 'redirect'])
        ->name('auth.social.redirect');
    Route::get('/auth/social/{provider}/callback', [SocialiteAuthController::class, 'callback'])
        ->name('auth.social.callback');
});

// Email Verification Routes (Signed URLs)
Route::group(['prefix' => 'email'], function () {
    Route::get('/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::get('/verify/error', [VerifyEmailController::class, 'error'])
        ->name('verification.error');
});

// Public Doctor Routes (No Auth Required)
Route::group(['prefix' => 'doctors'], function () {
    Route::get('/', [DoctorController::class, 'index']);
    Route::get('/specialities', [DoctorController::class, 'specialities']);
    Route::get('/locations', [DoctorController::class, 'locations']);
    Route::get('/search', [DoctorController::class, 'search']);
    Route::get('/{doctor}/availability', [AvailabilityController::class, 'getAvailability']);
    Route::get('/{doctor}/slots', [AppointmentController::class, 'getAvailableSlots']);
});

// Public Appointment Routes
Route::get('/appointments', [AppointmentController::class, 'getAppointments']);

// Authenticated Routes (Sanctum Protected)
Route::middleware(['auth:sanctum'])->group(function () {
    // Basic Auth Routes
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'role' => $request->user()->role ?? null
        ]);
    });

    // Email Verification for Authenticated Users
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware(['throttle:6,1'])
        ->name('verification.send');
    Route::get('/email/verify/check', function (Request $request) {
        return response()->json([
            'verified' => $request->user() && $request->user()->hasVerifiedEmail(),
        ]);
    });

    // Doctor profile completion (after social login)
    Route::post('/doctor/complete-profile', [DoctorProfileController::class, 'completeProfile'])
        ->middleware(['role:medecin']);

    // Routes requiring verified email and active status
    Route::middleware(['verified', 'active.user'])->group(function () {

        // Patient Routes
        Route::group(['prefix' => 'patient', 'middleware' => 'role:patient'], function () {
            Route::get('/profile', [PatientProfileController::class, 'show']);
            Route::put('/profile', [PatientProfileController::class, 'update']);
            Route::put('/profile/password', [PatientProfileController::class, 'updatePassword']);
            Route::post('/profile/photo', [PatientProfileController::class, 'updatePhoto']);

            // Patient Appointments
            Route::post('/appointments', [AppointmentController::class, 'bookAppointment']);
            Route::get('/appointments', [AppointmentController::class, 'getAppointments']);
            Route::patch('/appointments/{rendezvous}/status', [AppointmentController::class, 'updateAppointmentStatus']);
        });

        // Doctor Routes
        Route::group(['prefix' => 'doctor', 'middleware' => 'role:medecin'], function () {
            // Profile Management
            Route::get('/profile', [DoctorProfileController::class, 'show']);
            Route::put('/profile', [DoctorProfileController::class, 'update']);
            Route::put('/profile/password', [DoctorProfileController::class, 'updatePassword']);
            Route::post('/profile/photo', [DoctorProfileController::class, 'updatePhoto']);

            // Document Management
            Route::apiResource('/documents', DocumentController::class)
                ->except(['update']);

            // Appointments
            Route::get('/appointments', [AppointmentController::class, 'getAppointments']);
            Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);

            // Verified Doctor Routes
            Route::middleware(['verified.doctor'])->group(function () {
                Route::put('/{doctor}/schedule', [AvailabilityController::class, 'updateSchedule']);
                Route::post('/{doctor}/leaves', [AvailabilityController::class, 'createLeave']);
                Route::delete('/{doctor}/leaves/{leave}', [AvailabilityController::class, 'deleteLeave']);
            });
        });

        // Admin Routes
        Route::group(['prefix' => 'admin', 'middleware' => 'role:admin'], function () {
            // User Management
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users/admin', [UserController::class, 'storeAdmin']);
            Route::get('/users/{id}', [UserController::class, 'show']);
            Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
            Route::put('/users/{id}/password', [UserController::class, 'resetPassword']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
            Route::put('/admins/{id}/status', [UserController::class, 'updateAdminStatus']);

            // Doctor Verification
            Route::get('/doctors/pending', [DoctorVerificationController::class, 'pendingDoctors']);
            Route::get('/documents/pending', [DoctorVerificationController::class, 'pendingDocuments']);
            Route::get('/documents/{id}', [DoctorVerificationController::class, 'showDocument']);
            Route::post('/documents/{id}/approve', [DoctorVerificationController::class, 'approveDocument']);
            Route::post('/documents/{id}/reject', [DoctorVerificationController::class, 'rejectDocument']);
            Route::post('/doctors/{id}/verify', [DoctorVerificationController::class, 'verifyDoctor']);
            Route::post('/doctors/{id}/revoke', [DoctorVerificationController::class, 'revokeVerification']);
        });
    });
});

// Appointment booking with specific doctor (requires auth)
Route::middleware(['auth:sanctum', 'verified', 'active.user'])->group(function () {
    Route::post('/doctors/{doctorId}/appointments', [AppointmentController::class, 'bookAppointment']);
});
