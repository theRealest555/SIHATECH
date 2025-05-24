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
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\DoctorVerificationController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Guest Routes (No Authentication Required)
Route::middleware('guest')->group(function () {
    // Authentication Routes
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('api.register');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('api.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('api.admin.login');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('api.password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('api.password.update');

    // Social Authentication
    Route::get('/auth/social/{provider}/redirect', [SocialiteAuthController::class, 'redirect'])
        ->name('auth.social.redirect')
        ->where('provider', 'google|facebook');
    Route::get('/auth/social/{provider}/callback', [SocialiteAuthController::class, 'callback'])
        ->name('auth.social.callback')
        ->where('provider', 'google|facebook');
});

// Public Routes (No Authentication Required)
Route::group(['prefix' => 'public'], function () {
    // Public Doctor Information
    Route::get('/doctors', [DoctorController::class, 'index'])->name('api.public.doctors.index');
    Route::get('/doctors/specialities', [DoctorController::class, 'specialities'])->name('api.public.doctors.specialities');
    Route::get('/doctors/locations', [DoctorController::class, 'locations'])->name('api.public.doctors.locations');
    Route::get('/doctors/search', [DoctorController::class, 'search'])->name('api.public.doctors.search');
    Route::get('/doctors/{doctor}/availability', [AvailabilityController::class, 'getAvailability'])->name('api.public.doctors.availability');
    Route::get('/doctors/{doctor}/slots', [AppointmentController::class, 'getAvailableSlots'])->name('api.public.doctors.slots');
});

// Email Verification Routes (Signed URLs)
Route::group(['prefix' => 'email'], function () {
    Route::get('/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::get('/verify/error', [VerifyEmailController::class, 'error'])
        ->name('verification.error');
});

// Authenticated Routes (Sanctum Protected)
Route::middleware(['auth:sanctum'])->group(function () {
    // Basic Auth Routes
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.logout');
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('api.admin.logout');

    // Current User Info
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $userData = [
            'id' => $user->id,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at,
            'photo' => $user->photo,
            'telephone' => $user->telephone,
        ];

        // Add role-specific data
        if ($user->role === 'medecin' && $user->doctor) {
            $userData['doctor'] = [
                'id' => $user->doctor->id,
                'is_verified' => $user->doctor->is_verified,
                'speciality' => $user->doctor->speciality,
            ];
        } elseif ($user->role === 'patient' && $user->patient) {
            $userData['patient'] = [
                'id' => $user->patient->id,
            ];
        } elseif ($user->role === 'admin' && $user->admin) {
            $userData['admin'] = [
                'id' => $user->admin->id,
                'admin_status' => $user->admin->admin_status,
            ];
        }

        return response()->json([
            'user' => $userData,
            'role' => $user->role
        ]);
    })->name('api.user');

    // Email Verification for Authenticated Users
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware(['throttle:6,1'])
        ->name('verification.send');

    Route::get('/email/verify/check', function (Request $request) {
        return response()->json([
            'verified' => $request->user() && $request->user()->hasVerifiedEmail(),
        ]);
    })->name('api.email.verify.check');

    // Doctor profile completion (after social login) - Before verification required
    Route::post('/doctor/complete-profile', [DoctorProfileController::class, 'completeProfile'])
        ->middleware(['role:medecin'])
        ->name('api.doctor.complete-profile');

    // Routes requiring verified email and active status
    Route::middleware(['verified', 'active.user'])->group(function () {

        // Patient Routes
        Route::group(['prefix' => 'patient', 'middleware' => 'role:patient'], function () {
            // Profile Management
            Route::get('/profile', [PatientProfileController::class, 'show'])->name('api.patient.profile.show');
            Route::put('/profile', [PatientProfileController::class, 'update'])->name('api.patient.profile.update');
            Route::put('/profile/password', [PatientProfileController::class, 'updatePassword'])->name('api.patient.profile.password');
            Route::post('/profile/photo', [PatientProfileController::class, 'updatePhoto'])->name('api.patient.profile.photo');

            // Patient Appointments
            Route::get('/appointments', [AppointmentController::class, 'getAppointments'])->name('api.patient.appointments.index');
            Route::post('/doctors/{doctorId}/appointments', [AppointmentController::class, 'bookAppointment'])->name('api.patient.appointments.book');
            Route::patch('/appointments/{rendezvous}/status', [AppointmentController::class, 'updateAppointmentStatus'])->name('api.patient.appointments.update-status');
        });

        // Doctor Routes
        Route::group(['prefix' => 'doctor', 'middleware' => 'role:medecin'], function () {
            // Profile Management
            Route::get('/profile', [DoctorProfileController::class, 'show'])->name('api.doctor.profile.show');
            Route::put('/profile', [DoctorProfileController::class, 'update'])->name('api.doctor.profile.update');
            Route::put('/profile/password', [DoctorProfileController::class, 'updatePassword'])->name('api.doctor.profile.password');
            Route::post('/profile/photo', [DoctorProfileController::class, 'updatePhoto'])->name('api.doctor.profile.photo');

            // Document Management
            Route::get('/documents', [DocumentController::class, 'index'])->name('api.doctor.documents.index');
            Route::post('/documents', [DocumentController::class, 'store'])->name('api.doctor.documents.store');
            Route::get('/documents/{id}', [DocumentController::class, 'show'])->name('api.doctor.documents.show');
            Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->name('api.doctor.documents.destroy');

            // Appointments Management
            Route::get('/appointments', [AppointmentController::class, 'getAppointments'])->name('api.doctor.appointments.index');
            Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])->name('api.doctor.appointments.update-status');

            // Availability Management (for all doctors, verification check inside controller)
            Route::get('/availability', function(Request $request) {
                $doctor = $request->user()->doctor;
                return app(AvailabilityController::class)->getAvailability($request, $doctor);
            })->name('api.doctor.availability.show');

            // Verified Doctor Routes
            Route::middleware(['verified.doctor'])->group(function () {
                Route::put('/schedule', function(Request $request) {
                    $doctor = $request->user()->doctor;
                    return app(AvailabilityController::class)->updateSchedule($request, $doctor);
                })->name('api.doctor.schedule.update');

                Route::post('/leaves', function(Request $request) {
                    $doctor = $request->user()->doctor;
                    return app(AvailabilityController::class)->createLeave($request, $doctor);
                })->name('api.doctor.leaves.store');

                Route::delete('/leaves/{leave}', function(Request $request, $leaveId) {
                    $doctor = $request->user()->doctor;
                    $leave = \App\Models\Leave::findOrFail($leaveId);
                    return app(AvailabilityController::class)->deleteLeave($request, $doctor, $leave);
                })->name('api.doctor.leaves.destroy');
            });
        });

        // Admin Routes
        Route::group(['prefix' => 'admin', 'middleware' => 'role:admin'], function () {
            // Dashboard
            Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('api.admin.dashboard');

            // User Management
            Route::get('/users', [UserController::class, 'index'])->name('api.admin.users.index');
            Route::post('/users/admin', [UserController::class, 'storeAdmin'])->name('api.admin.users.store-admin');
            Route::get('/users/{id}', [UserController::class, 'show'])->name('api.admin.users.show');
            Route::put('/users/{id}/status', [UserController::class, 'updateStatus'])->name('api.admin.users.update-status');
            Route::put('/users/{id}/password', [UserController::class, 'resetPassword'])->name('api.admin.users.reset-password');
            Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('api.admin.users.destroy');
            Route::put('/admins/{id}/status', [UserController::class, 'updateAdminStatus'])->name('api.admin.admins.update-status');

            // User Data Export
            Route::get('/users/export', [AdminController::class, 'exportUserData'])->name('api.admin.users.export');

            // Reviews Management
            Route::get('/reviews/pending', [AdminController::class, 'getPendingReviews'])->name('api.admin.reviews.pending');
            Route::post('/reviews/{review}/moderate', [AdminController::class, 'moderateReview'])->name('api.admin.reviews.moderate');

            // Doctor Verification
            Route::get('/doctors/pending', [DoctorVerificationController::class, 'pendingDoctors'])->name('api.admin.doctors.pending');
            Route::get('/documents/pending', [DoctorVerificationController::class, 'pendingDocuments'])->name('api.admin.documents.pending');
            Route::get('/documents/{id}', [DoctorVerificationController::class, 'showDocument'])->name('api.admin.documents.show');
            Route::post('/documents/{id}/approve', [DoctorVerificationController::class, 'approveDocument'])->name('api.admin.documents.approve');
            Route::post('/documents/{id}/reject', [DoctorVerificationController::class, 'rejectDocument'])->name('api.admin.documents.reject');
            Route::post('/doctors/{id}/verify', [DoctorVerificationController::class, 'verifyDoctor'])->name('api.admin.doctors.verify');
            Route::post('/doctors/{id}/revoke', [DoctorVerificationController::class, 'revokeVerification'])->name('api.admin.doctors.revoke');

            // Reports and Analytics
            Route::get('/reports/financial', [ReportController::class, 'financialStats'])->name('api.admin.reports.financial');
            Route::get('/reports/appointments', [ReportController::class, 'rendezvousStats'])->name('api.admin.reports.appointments');
            Route::get('/reports/export/financial', [ReportController::class, 'exportFinancialReport'])->name('api.admin.reports.export.financial');
        });

        // Subscription Routes (for all authenticated users)
        Route::group(['prefix' => 'subscriptions'], function () {
            Route::get('/plans', [SubscriptionController::class, 'getPlans'])->name('api.subscriptions.plans');
            Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('api.subscriptions.subscribe');
            Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('api.subscriptions.cancel');
            Route::get('/current', [SubscriptionController::class, 'getUserSubscription'])->name('api.subscriptions.current');
        });

        // General Appointments Route (accessible by doctors and patients)
        Route::get('/appointments', [AppointmentController::class, 'getAppointments'])->name('api.appointments.index');
    });
});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'auth' => '/api/login, /api/register, /api/logout',
            'public' => '/api/public/doctors, /api/public/doctors/search',
            'patient' => '/api/patient/profile, /api/patient/appointments',
            'doctor' => '/api/doctor/profile, /api/doctor/appointments, /api/doctor/documents',
            'admin' => '/api/admin/dashboard, /api/admin/users, /api/admin/doctors/pending'
        ]
    ], 404);
});
