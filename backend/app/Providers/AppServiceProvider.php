<?php

namespace App\Providers;

use App\Models\Avis;
use App\Models\Doctor;
use App\Models\Rendezvous;
use App\Observers\ReviewObserver;
use App\Services\AccountCredentials;
use App\Services\StripePaymentService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the Stripe payment service as a singleton
        $this->app->singleton(StripePaymentService::class, function ($app) {
            return new StripePaymentService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        Event::listen(Login::class, function (Login $event) {
            $request = request();
            if ($event->guard === 'web' && $request->hasSession()) {
                app(AccountCredentials::class)->stampSession($request, $event->user);
            }
        });
        // Configure password reset URL
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/reset-password/$token?email=".rawurlencode($notifiable->getEmailForPasswordReset());
        });

        // Register model observers
        Avis::observe(ReviewObserver::class);

        // Add custom validation rules
        $this->addCustomValidationRules();
    }

    /**
     * Add custom validation rules
     */
    protected function addCustomValidationRules(): void
    {
        // Validation rule for checking if a time slot is available
        Validator::extend('available_slot', function ($attribute, $value, $parameters, $validator) {
            // $parameters[0] should be the doctor_id
            if (! isset($parameters[0])) {
                return false;
            }

            $doctorId = $parameters[0];
            $dateTime = Carbon::parse($value);

            // Check if slot is available
            $exists = Rendezvous::where('doctor_id', $doctorId)
                ->where('date_heure', $dateTime)
                ->whereNotIn('statut', ['annulé', 'terminé', 'no_show'])
                ->exists();

            return ! $exists;
        }, 'The selected time slot is not available.');

        // Validation rule for checking if doctor is available on a specific day
        Validator::extend('doctor_available_day', function ($attribute, $value, $parameters, $validator) {
            if (! isset($parameters[0])) {
                return false;
            }

            $doctorId = $parameters[0];
            $date = Carbon::parse($value);
            $dayOfWeek = strtolower($date->format('l'));

            // Map to French days
            $dayMap = [
                'monday' => 'lundi',
                'tuesday' => 'mardi',
                'wednesday' => 'mercredi',
                'thursday' => 'jeudi',
                'friday' => 'vendredi',
                'saturday' => 'samedi',
                'sunday' => 'dimanche',
            ];

            $frenchDay = $dayMap[$dayOfWeek] ?? $dayOfWeek;

            $doctor = Doctor::find($doctorId);
            if (! $doctor) {
                return false;
            }

            $horaires = $doctor->horaires ?? [];

            return isset($horaires[$frenchDay]) && ! empty($horaires[$frenchDay]);
        }, 'The doctor is not available on the selected day.');
    }

    /**
     * Configure the rate limiting for the application.
     */
    protected function configureRateLimiting()
    {
        foreach (['admin-creation', 'admin-credential-reset'] as $limiter) {
            RateLimiter::for($limiter, fn (Request $request) => Limit::perMinute(3)->by($request->user()?->id ?? $request->ip()));
        }
        RateLimiter::for('password-reset', function (Request $request) {
            $email = $request->input('email');
            $key = hash('sha256', is_string($email) ? Str::lower(trim($email)) : 'invalid');

            return [
                Limit::perMinute(10)->by('password-reset-ip:'.$request->ip()),
                Limit::perMinute(5)->by('password-reset-email:'.$key),
            ];
        });
        RateLimiter::for('password-recovery', function (Request $request) {
            $email = $request->input('email');
            $key = hash('sha256', is_string($email) ? Str::lower(trim($email)) : 'invalid');

            return [
                Limit::perMinute(10)->by('password-recovery-ip:'.$request->ip()),
                Limit::perMinute(3)->by('password-recovery-email:'.$key),
            ];
        });
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
