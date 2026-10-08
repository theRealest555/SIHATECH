<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\DoctorVerificationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Doctor\DocumentController;
use App\Http\Controllers\Doctor\ProfileController as DoctorProfileController;
use App\Http\Controllers\Doctor\StatisticsController as DoctorStatisticsController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\Patient\ProfileController as PatientProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Services\SocialProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Webhook Routes (No CSRF protection)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])
    ->name('webhooks.stripe');

// Guest Routes (No Authentication Required)
Route::middleware('guest')->group(function () {
    // Authentication Routes
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('api.register');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('api.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('api.admin.login');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-recovery')->name('api.password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('api.password.update');

});

// Public Routes (No Authentication Required)
Route::group(['prefix' => 'public'], function () {
    Route::get('/auth/providers', fn () => response()->json(['data' => app(SocialProviders::class)->publicOptions()], 200, ['Cache-Control' => 'no-store']));
    // Public Doctor Information
    Route::get('/doctors', [DoctorController::class, 'index'])->name('api.public.doctors.index');
    Route::get('/doctors/search', [DoctorController::class, 'search'])->name('api.public.doctors.search');
    Route::get('/doctors/{doctor}', [DoctorController::class, 'show'])->name('api.public.doctors.show')->where('doctor', '[0-9]+');
    Route::get('/doctors/{doctor}/statistics', [DoctorController::class, 'statistics'])->name('api.public.doctors.statistics')->where('doctor', '[0-9]+');
    Route::get('/doctors/{doctor}/availability', [AvailabilityController::class, 'getAvailability'])->name('api.public.doctors.availability')->where('doctor', '[0-9]+');
    Route::get('/doctors/{doctor}/slots', [AppointmentController::class, 'getAvailableSlots'])->name('api.public.doctors.slots')->where('doctor', '[0-9]+');

    // Public Resources
    Route::get('/specialities', [DoctorController::class, 'specialities'])->name('api.public.specialities');
    Route::get('/languages', [DoctorController::class, 'languages'])->name('api.public.languages');
    Route::get('/locations', [DoctorController::class, 'locations'])->name('api.public.locations');
});

// Authenticated Routes (Sanctum Protected)
Route::middleware(['auth:sanctum', 'current.session'])->group(function () {
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
                'average_rating' => $user->doctor->average_rating,
                'total_reviews' => $user->doctor->total_reviews,
            ];
            $userData['doctor_profile_completed'] = $user->doctor->speciality_id !== null;
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
            'role' => $user->role,
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

            Route::get('/reviews', [ReviewController::class, 'index']);
            Route::get('/appointments/{appointment}/review', [ReviewController::class, 'context'])->whereNumber('appointment');
            Route::post('/appointments/{appointment}/review', [ReviewController::class, 'store'])->whereNumber('appointment')->middleware('throttle:10,1');

            // Patient Appointments
            Route::get('/appointments', [AppointmentController::class, 'getAppointments'])->name('api.patient.appointments.index');
            Route::post('/doctors/{doctorId}/appointments', [AppointmentController::class, 'bookAppointment'])->name('api.patient.appointments.book')->where('doctorId', '[0-9]+');
            Route::patch('/appointments/{rendezvous}/status', [AppointmentController::class, 'updateAppointmentStatus'])->name('api.patient.appointments.update-status')->where('rendezvous', '[0-9]+');
        });

        // Doctor Routes
        Route::group(['prefix' => 'doctor', 'middleware' => 'role:medecin'], function () {
            // Profile Management
            Route::get('/profile', [DoctorProfileController::class, 'show'])->name('api.doctor.profile.show');
            Route::put('/profile', [DoctorProfileController::class, 'update'])->name('api.doctor.profile.update');
            Route::put('/profile/password', [DoctorProfileController::class, 'updatePassword'])->name('api.doctor.profile.password');
            Route::post('/profile/photo', [DoctorProfileController::class, 'updatePhoto'])->name('api.doctor.profile.photo');
            Route::put('/languages', [DoctorController::class, 'updateLanguages'])->name('api.doctor.languages.update');

            // Document Management
            Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->whereNumber('document');
            Route::get('/documents', [DocumentController::class, 'index'])->name('api.doctor.documents.index');
            Route::post('/documents', [DocumentController::class, 'store'])->name('api.doctor.documents.store');
            Route::get('/documents/{id}', [DocumentController::class, 'show'])->name('api.doctor.documents.show')->where('id', '[0-9]+');
            Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->name('api.doctor.documents.destroy')->where('id', '[0-9]+');

            // Appointments Management
            Route::get('/appointments', [AppointmentController::class, 'getAppointments'])->name('api.doctor.appointments.index');
            Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])->name('api.doctor.appointments.update-status')->where('id', '[0-9]+');
            Route::post('/appointments/{id}/no-show', [AppointmentController::class, 'markAsNoShow'])->name('api.doctor.appointments.mark-no-show')->where('id', '[0-9]+');
            Route::get('/appointments/no-show-stats', [AppointmentController::class, 'getNoShowStats'])->name('api.doctor.appointments.no-show-stats');

            // Statistics
            Route::get('/stats', [DoctorStatisticsController::class, 'index'])->name('api.doctor.stats');
            Route::get('/stats/appointments', [DoctorStatisticsController::class, 'appointments'])->name('api.doctor.stats.appointments');
            Route::get('/stats/patients', [DoctorStatisticsController::class, 'patients'])->name('api.doctor.stats.patients');
            Route::get('/stats/revenue', [DoctorStatisticsController::class, 'revenue'])->name('api.doctor.stats.revenue');
            Route::get('/stats/export', [DoctorStatisticsController::class, 'export'])->name('api.doctor.stats.export');

            // Availability Management (for authenticated doctor)
            Route::get('/availability', [AvailabilityController::class, 'getAvailability'])->name('api.doctor.availability.show');

            // Verified Doctor Routes
            Route::middleware(['verified.doctor'])->group(function () {
                Route::put('/schedule', [AvailabilityController::class, 'updateSchedule'])->name('api.doctor.schedule.update');
                Route::post('/leaves', [AvailabilityController::class, 'createLeave'])->name('api.doctor.leaves.store');
                Route::delete('/leaves/{leave}', [AvailabilityController::class, 'deleteLeave'])->name('api.doctor.leaves.destroy')->where('leave', '[0-9]+');
            });
        });

        // Admin Routes
        Route::group(['prefix' => 'admin', 'middleware' => 'role:admin'], function () {
            // Dashboard
            Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('api.admin.dashboard');
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('api.admin.audit-logs');
            foreach (['specialities', 'languages'] as $catalogue) {
                Route::get('/'.$catalogue, [CatalogueController::class, 'index'])->defaults('catalogue', $catalogue);
                Route::post('/'.$catalogue, [CatalogueController::class, 'store'])->defaults('catalogue', $catalogue);
                Route::put('/'.$catalogue.'/{id}', [CatalogueController::class, 'update'])->whereNumber('id')->defaults('catalogue', $catalogue);
            }

            // User Data Export (Define specific route before parameterized one)
            Route::get('/users/export', [AdminController::class, 'exportUserData'])->name('api.admin.users.export');

            // User Management
            Route::get('/users', [UserController::class, 'index'])->name('api.admin.users.index');
            Route::post('/users/admin', [UserController::class, 'storeAdmin'])->middleware('throttle:admin-creation')->name('api.admin.users.store-admin');
            Route::get('/users/{id}', [UserController::class, 'show'])->name('api.admin.users.show')->where('id', '[0-9]+');
            Route::put('/users/{id}/status', [UserController::class, 'updateStatus'])->name('api.admin.users.update-status')->where('id', '[0-9]+');
            Route::put('/users/{id}/password', [UserController::class, 'resetPassword'])->middleware('throttle:admin-credential-reset')->name('api.admin.users.reset-password')->where('id', '[0-9]+');
            Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('api.admin.users.destroy')->where('id', '[0-9]+');
            Route::put('/admins/{id}/status', [UserController::class, 'updateAdminStatus'])->name('api.admin.admins.update-status')->where('id', '[0-9]+');

            Route::get('/subscription-plans', [PlanController::class, 'index']);
            Route::post('/subscription-plans', [PlanController::class, 'store']);
            Route::put('/subscription-plans/{id}', [PlanController::class, 'update'])->whereNumber('id');

            // Reviews Management
            Route::get('/reviews', [ReviewController::class, 'adminIndex']);
            Route::get('/reviews/pending', [ReviewController::class, 'pending'])->name('api.admin.reviews.pending');
            Route::post('/reviews/{review}/moderate', [ReviewController::class, 'moderate'])->name('api.admin.reviews.moderate')->where('review', '[0-9]+');

            // Doctor Verification
            Route::get('/documents/{document}/download', [DoctorVerificationController::class, 'downloadDocument'])->whereNumber('document');
            Route::get('/doctors/pending', [DoctorVerificationController::class, 'pendingDoctors'])->name('api.admin.doctors.pending');
            Route::get('/doctors', [DoctorVerificationController::class, 'index']);
            Route::get('/doctors/{doctor}', [DoctorVerificationController::class, 'show'])->whereNumber('doctor');
            Route::get('/documents/pending', [DoctorVerificationController::class, 'pendingDocuments'])->name('api.admin.documents.pending');
            Route::get('/documents/{id}', [DoctorVerificationController::class, 'showDocument'])->name('api.admin.documents.show')->where('id', '[0-9]+');
            Route::post('/documents/{id}/approve', [DoctorVerificationController::class, 'approveDocument'])->name('api.admin.documents.approve')->where('id', '[0-9]+');
            Route::post('/documents/{id}/reject', [DoctorVerificationController::class, 'rejectDocument'])->name('api.admin.documents.reject')->where('id', '[0-9]+');
            Route::post('/doctors/{id}/verify', [DoctorVerificationController::class, 'verifyDoctor'])->name('api.admin.doctors.verify')->where('id', '[0-9]+');
            Route::post('/doctors/{id}/revoke', [DoctorVerificationController::class, 'revokeVerification'])->name('api.admin.doctors.revoke')->where('id', '[0-9]+');

            // Appointment Management
            Route::post('/appointments/{id}/no-show', [AppointmentController::class, 'markAsNoShow'])->name('api.admin.appointments.mark-no-show')->where('id', '[0-9]+');

            // Reports and Analytics
            Route::get('/reports/financial', [ReportController::class, 'financialStats'])->name('api.admin.reports.financial');
            Route::get('/reports/appointments', [ReportController::class, 'rendezvousStats'])->name('api.admin.reports.appointments');
            Route::get('/reports/export/financial', [ReportController::class, 'exportFinancialReport'])->name('api.admin.reports.export.financial');
            Route::get('/reports/export/appointments', [ReportController::class, 'exportAppointmentReport'])->name('api.admin.reports.export.appointments');
        });

        // Subscription Routes (for all authenticated users)
        Route::group(['prefix' => 'subscriptions'], function () {

            Route::get('/setup-intent', [SubscriptionController::class, 'getSetupIntent'])->name('api.subscriptions.setup-intent');
            Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('api.subscriptions.subscribe');
            Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('api.subscriptions.cancel');
            Route::put('/payment-method', [SubscriptionController::class, 'updatePaymentMethod'])->name('api.subscriptions.update-payment-method');
            Route::get('/current', [SubscriptionController::class, 'getUserSubscription'])->name('api.subscriptions.current');
            Route::get('/history', [SubscriptionController::class, 'getSubscriptionHistory'])->name('api.subscriptions.history');
            Route::get('/payments', [SubscriptionController::class, 'getPaymentHistory'])->name('api.subscriptions.payments');
        });

        // General Appointments Route (accessible by doctors and patients, filtered by role in controller)
        Route::get('/appointments', [AppointmentController::class, 'getAppointments'])->name('api.appointments.index');
    });
});

Route::get('/subscriptions/plans', [SubscriptionController::class, 'getPlans'])->name('api.subscriptions.plans');

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'auth' => '/api/login, /api/register, /api/logout',
            'public' => '/api/public/doctors, /api/public/doctors/search, /api/public/languages',
            'patient' => '/api/patient/profile, /api/patient/appointments',
            'doctor' => '/api/doctor/profile, /api/doctor/appointments, /api/doctor/documents, /api/doctor/stats',
            'admin' => '/api/admin/dashboard, /api/admin/users, /api/admin/doctors/pending',
            'subscriptions' => '/api/subscriptions/plans, /api/subscriptions/current',
        ],
    ], 404);
});
