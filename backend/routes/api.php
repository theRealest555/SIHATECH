<?php

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Api\AppointmentController;
// use App\Http\Controllers\Api\AuthController;
// use App\Http\Controllers\Api\AvailabilityController;
// use App\Http\Controllers\DoctorController;

// Route::post('/login', [AuthController::class, 'login']);
// Route::get('/doctors', [DoctorController::class, 'index']);
// Route::get('/doctors/specialities', [DoctorController::class, 'specialities']);
// Route::get('/doctors/locations', [DoctorController::class, 'locations']);
// Route::get('/doctors/search', [DoctorController::class, 'search']);
// Route::post('/appointments', [AppointmentController::class, 'bookAppointment']);
// Route::put('/appointments/{rendezvous}/status', [AppointmentController::class, 'updateAppointmentStatus']);
// Route::get('/appointments', [AppointmentController::class, 'getAppointments']);
// Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
// Route::get('/doctors/{doctorId}/availability', [DoctorController::class, 'availability']);
// Route::get('/doctors/{doctorId}/slots', [DoctorController::class, 'slots']);
// Route::post('/doctors/{doctorId}/appointments', [DoctorController::class, 'bookAppointment']);
// Route::post('/doctors/{doctorId}/leaves', [DoctorController::class, 'createLeave']);
// Route::delete('/doctors/{doctorId}/leaves/{leaveId}', [DoctorController::class, 'deleteLeave']);
// Route::post('/doctors/{doctorId}/schedule', [DoctorController::class, 'updateSchedule']);
// Route::prefix('doctors')->group(function () {
// Route::post('{doctorId}/update-schedule', [DoctorController::class, 'updateSchedule']);
// });

// // Protected routes (for future use with auth)
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/user', function (Request $request) {
//         return $request->user();
//     });
// });



// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Auth\AuthenticatedSessionController;
// use App\Http\Controllers\Auth\RegisteredUserController;
// use App\Http\Controllers\Auth\AdminAuthController;
// use App\Http\Controllers\Auth\VerifyEmailController;
// use App\Http\Controllers\Auth\EmailVerificationNotificationController;
// use App\Http\Controllers\Patient\ProfileController as PatientProfileController;
// use App\Http\Controllers\Doctor\ProfileController as DoctorProfileController;
// use App\Http\Controllers\Doctor\DocumentController;
// use App\Http\Controllers\Admin\UserController;
// use App\Http\Controllers\Admin\DoctorVerificationController;
// use App\Http\Controllers\Api\AppointmentController;
// use App\Http\Controllers\Api\AvailabilityController;
// use App\Http\Controllers\DoctorController;

// /*
// |--------------------------------------------------------------------------
// | API Routes
// |--------------------------------------------------------------------------
// */

// // Public Routes (No Authentication Required)
// Route::post('/register', [RegisteredUserController::class, 'store']);
// Route::post('/login', [AuthenticatedSessionController::class, 'store']); // Login for patients and doctors
// Route::post('/admin/login', [AdminAuthController::class, 'login']);

// // Doctor-specific login (optional, if separate logic is needed)
// Route::post('/doctor/login', [AuthenticatedSessionController::class, 'store']); // Reuse for doctors

// // Email Verification Routes (Public but Signed)
// Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
//     ->middleware(['signed', 'throttle:6,1'])
//     ->name('verification.verify');

// Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
//     ->middleware(['auth:sanctum', 'throttle:6,1'])
//     ->name('verification.send');

// // Public Doctor and Appointment Routes (Accessible Without Login)
// Route::prefix('doctors')->group(function () {
//     Route::get('/', [DoctorController::class, 'index']); // List all doctors
//     Route::get('/specialities', [DoctorController::class, 'specialities']); // List specialities
//     Route::get('/locations', [DoctorController::class, 'locations']); // List locations
//     Route::get('/search', [DoctorController::class, 'search']); // Search doctors
//     Route::get('/{doctorId}', [DoctorController::class, 'show']); // Doctor details
//     Route::get('/{doctorId}/availability', [AvailabilityController::class, 'getAvailability']); // Doctor availability
//     Route::get('/{doctorId}/slots', [AppointmentController::class, 'getAvailableSlots']); // Available slots
// });

// // Protected Routes (Require Authentication)
// Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
//     // Common Routes for All Authenticated Users
//     Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
//     Route::get('/user', function (Request $request) {
//         return $request->user();
//     });

//     // Patient Routes
//     Route::middleware('role:patient')->prefix('patient')->group(function () {
//         // Profile Management
//         Route::get('/profile', [PatientProfileController::class, 'show']);
//         Route::put('/profile', [PatientProfileController::class, 'update']);
//         Route::put('/profile/password', [PatientProfileController::class, 'updatePassword']);
//         Route::post('/profile/photo', [PatientProfileController::class, 'updatePhoto']);

//         // Appointment Management for Patients
//         Route::prefix('appointments')->group(function () {
//             Route::post('/', [AppointmentController::class, 'bookAppointment']); // Book an appointment
//             Route::get('/', [AppointmentController::class, 'getAppointments']); // List patient's appointments
//             Route::patch('/{rendezvous}/status', [AppointmentController::class, 'updateAppointmentStatus']); // Update status (e.g., cancel)
//         });
//     });

//     // Doctor Routes
//     Route::middleware('role:medecin')->prefix('doctor')->group(function () {
//         // Profile Management
//         Route::get('/profile', [DoctorProfileController::class, 'show']);
//         Route::put('/profile', [DoctorProfileController::class, 'update']);
//         Route::put('/profile/password', [DoctorProfileController::class, 'updatePassword']);
//         Route::post('/profile/photo', [DoctorProfileController::class, 'updatePhoto']);

//         // Document Management
//         Route::get('/documents', [DocumentController::class, 'index']);
//         Route::post('/documents', [DocumentController::class, 'store']);
//         Route::get('/documents/{id}', [DocumentController::class, 'show']);
//         Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

//         // Appointment Management (Accessible to All Doctors)
//         Route::prefix('appointments')->group(function () {
//             Route::get('/', [AppointmentController::class, 'getAppointments']); // List doctor's appointments
//             Route::patch('/{id}/status', [AppointmentController::class, 'updateStatus']); // Modify appointment status
//         });

//         // Routes for Verified Doctors
//         Route::middleware('verified.doctor')->group(function () {
//             // Schedule and Leave Management
//             Route::post('/schedule', [DoctorController::class, 'updateSchedule']);
//             Route::post('/leaves', [DoctorController::class, 'createLeave']);
//             Route::delete('/leaves/{leaveId}', [DoctorController::class, 'deleteLeave']);
//         });
//     });

//     // Admin Routes
//     Route::middleware('role:admin')->prefix('admin')->group(function () {
//         // Admin Auth
//         Route::post('/logout', [AdminAuthController::class, 'logout']);

//         // User Management
//         Route::get('/users', [UserController::class, 'index']);
//         Route::post('/users/admin', [UserController::class, 'storeAdmin']);
//         Route::get('/users/{id}', [UserController::class, 'show']);
//         Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
//         Route::put('/users/{id}/password', [UserController::class, 'resetPassword']);
//         Route::delete('/users/{id}', [UserController::class, 'destroy']);

//         // Doctor Verification
//         Route::get('/doctors/pending', [DoctorVerificationController::class, 'pendingDoctors']);
//         Route::get('/documents/pending', [DoctorVerificationController::class, 'pendingDocuments']);
//         Route::get('/documents/{id}', [DoctorVerificationController::class, 'showDocument']);
//         Route::post('/documents/{id}/approve', [DoctorVerificationController::class, 'approveDocument']);
//         Route::post('/documents/{id}/reject', [DoctorVerificationController::class, 'rejectDocument']);
//         Route::post('/doctors/{id}/verify', [DoctorVerificationController::class, 'verifyDoctor']);
//         Route::post('/doctors/{id}/revoke', [DoctorVerificationController::class, 'revokeVerification']);
//     });
// });




// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Auth\AuthenticatedSessionController;
// use App\Http\Controllers\Auth\RegisteredUserController;
// use App\Http\Controllers\Auth\AdminAuthController;
// use App\Http\Controllers\Auth\VerifyEmailController;
// use App\Http\Controllers\Auth\EmailVerificationNotificationController;
// use App\Http\Controllers\Patient\ProfileController as PatientProfileController;
// use App\Http\Controllers\Doctor\ProfileController as DoctorProfileController;
// use App\Http\Controllers\Doctor\DocumentController;
// use App\Http\Controllers\Admin\UserController;
// use App\Http\Controllers\Admin\DoctorVerificationController;
// use App\Http\Controllers\Api\AppointmentController;
// use App\Http\Controllers\DoctorController;

// /*
// |--------------------------------------------------------------------------
// | API Routes
// |--------------------------------------------------------------------------
// */

// // Public Routes (No Authentication Required)
// Route::post('/register', [RegisteredUserController::class, 'store']);
// Route::post('/login', [AuthenticatedSessionController::class, 'store']);
// Route::post('/admin/login', [AdminAuthController::class, 'login']);

// // Email Verification Routes
// Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
//     ->middleware(['signed', 'throttle:6,1'])
//     ->name('verification.verify');

// Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
//     ->middleware(['auth:sanctum', 'throttle:6,1'])
//     ->name('verification.send');

// // Public Doctor Routes (Accessible Without Login)
// Route::prefix('doctors')->group(function () {
//     Route::get('/', [DoctorController::class, 'index']);
//     Route::get('/specialities', [DoctorController::class, 'specialities']);
//     Route::get('/locations', [DoctorController::class, 'locations']);
//     Route::get('/search', [DoctorController::class, 'search']);
//     Route::get('/{doctorId}/availability', [DoctorController::class, 'availability']);
//     Route::get('/{doctorId}/slots', [DoctorController::class, 'slots']);
//     // Moved from protected section for testing
//     Route::post('/{doctorId}/appointments', [DoctorController::class, 'bookAppointment']);
//     Route::post('/{doctorId}/schedule', [DoctorController::class, 'updateSchedule']);
//     Route::post('/{doctorId}/leaves', [DoctorController::class, 'createLeave']);
//     Route::delete('/{doctorId}/leaves/{leaveId}', [DoctorController::class, 'deleteLeave']);
// });

// // Public Appointment Routes (Accessible Without Login)
// Route::prefix('appointments')->group(function () {
//     Route::post('/', [AppointmentController::class, 'bookAppointment']);
//     Route::put('/{rendezvous}/status', [AppointmentController::class, 'updateAppointmentStatus']);
//     Route::get('/', [AppointmentController::class, 'getAppointments']);
//     Route::patch('/{id}/status', [AppointmentController::class, 'updateStatus']);
// });

// // Protected Routes (Require Authentication)
// Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
//     // Common Routes for All Authenticated Users
//     Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
//     Route::get('/user', function (Request $request) {
//         return $request->user();
//     });

//     // Patient Routes
//     Route::middleware('role:patient')->prefix('patient')->group(function () {
//         Route::get('/profile', [PatientProfileController::class, 'show']);
//         Route::put('/profile', [PatientProfileController::class, 'update']);
//         Route::put('/profile/password', [PatientProfileController::class, 'updatePassword']);
//         Route::post('/profile/photo', [PatientProfileController::class, 'updatePhoto']);

//         Route::prefix('appointments')->group(function () {
//             Route::post('/', [AppointmentController::class, 'bookAppointment']);
//             Route::get('/', [AppointmentController::class, 'getAppointments']);
//             Route::put('/{rendezvous}/status', [AppointmentController::class, 'updateAppointmentStatus']);
//         });
//     });

//     // Doctor Routes
//     Route::middleware('role:medecin')->prefix('doctor')->group(function () {
//         Route::get('/profile', [DoctorProfileController::class, 'show']);
//         Route::put('/profile', [DoctorProfileController::class, 'update']);
//         Route::put('/profile/password', [DoctorProfileController::class, 'updatePassword']);
//         Route::post('/profile/photo', [DoctorProfileController::class, 'updatePhoto']);
//         Route::get('/documents', [DocumentController::class, 'index']);
//         Route::post('/documents', [DocumentController::class, 'store']);
//         Route::get('/documents/{id}', [DocumentController::class, 'show']);
//         Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

//         Route::prefix('appointments')->group(function () {
//             Route::get('/', [AppointmentController::class, 'getAppointments']);
//             Route::patch('/{id}/status', [AppointmentController::class, 'updateStatus']);
//         });
//     });

//     // Admin Routes
//     Route::middleware('role:admin')->prefix('admin')->group(function () {
//         Route::post('/logout', [AdminAuthController::class, 'logout']);
//         Route::get('/users', [UserController::class, 'index']);
//         Route::post('/users/admin', [UserController::class, 'storeAdmin']);
//         Route::get('/users/{id}', [UserController::class, 'show']);
//         Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
//         Route::put('/users/{id}/password', [UserController::class, 'resetPassword']);
//         Route::delete('/users/{id}', [UserController::class, 'destroy']);
//         Route::get('/doctors/pending', [DoctorVerificationController::class, 'pendingDoctors']);
//         Route::get('/documents/pending', [DoctorVerificationController::class, 'pendingDocuments']);
//         Route::get('/documents/{id}', [DoctorVerificationController::class, 'showDocument']);
//         Route::post('/documents/{id}/approve', [DoctorVerificationController::class, 'approveDocument']);
//         Route::post('/documents/{id}/reject', [DoctorVerificationController::class, 'rejectDocument']);
//         Route::post('/doctors/{id}/verify', [DoctorVerificationController::class, 'verifyDoctor']);
//         Route::post('/doctors/{id}/revoke', [DoctorVerificationController::class, 'revokeVerification']);
//     });
// });


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
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\DoctorController;

// Public Routes (No Authentication Required)
Route::post('/register', [RegisteredUserController::class, 'store']);
Route::post('/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// Email Verification Routes
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
// Public Doctor Routes (Accessible Without Login)
Route::prefix('doctors')->group(function () {
    Route::get('/', [DoctorController::class, 'index']);
    Route::get('/specialities', [DoctorController::class, 'specialities']);
    Route::get('/locations', [DoctorController::class, 'locations']);
    Route::get('/search', [DoctorController::class, 'search']);
});

// Public Appointment Routes (Accessible Without Login)
Route::prefix('appointments')->group(function () {
    // Route::put('/{rendezvous}/status', [AppointmentController::class, 'updateAppointmentStatus']);
    Route::get('/', [AppointmentController::class, 'getAppointments']);
    // Route::patch('/{id}/status', [AppointmentController::class, 'updateStatus']);
});

// Protected Routes (Require Authentication)
Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
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

    // Admin Routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/admin', [UserController::class, 'storeAdmin']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
        Route::put('/users/{id}/password', [UserController::class, 'resetPassword']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::get('/doctors/pending', [DoctorVerificationController::class, 'pendingDoctors']);
        Route::get('/documents/pending', [DoctorVerificationController::class, 'pendingDocuments']);
        Route::get('/documents/{id}', [DoctorVerificationController::class, 'showDocument']);
        Route::post('/documents/{id}/approve', [DoctorVerificationController::class, 'approveDocument']);
        Route::post('/documents/{id}/reject', [DoctorVerificationController::class, 'rejectDocument']);
        Route::post('/doctors/{id}/verify', [DoctorVerificationController::class, 'verifyDoctor']);
        Route::post('/doctors/{id}/revoke', [DoctorVerificationController::class, 'revokeVerification']);
    });
});
