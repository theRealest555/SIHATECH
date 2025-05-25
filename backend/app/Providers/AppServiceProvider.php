<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use App\Models\Avis;
use App\Observers\ReviewObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the Stripe payment service as a singleton
        $this->app->singleton(\App\Services\StripePaymentService::class, function ($app) {
            return new \App\Services\StripePaymentService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure password reset URL
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
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
            if (!isset($parameters[0])) {
                return false;
            }

            $doctorId = $parameters[0];
            $dateTime = \Carbon\Carbon::parse($value);

            // Check if slot is available
            $exists = \App\Models\Rendezvous::where('doctor_id', $doctorId)
                ->where('date_heure', $dateTime)
                ->whereNotIn('statut', ['annulé', 'terminé', 'no_show'])
                ->exists();

            return !$exists;
        }, 'The selected time slot is not available.');

        // Validation rule for checking if doctor is available on a specific day
        Validator::extend('doctor_available_day', function ($attribute, $value, $parameters, $validator) {
            if (!isset($parameters[0])) {
                return false;
            }

            $doctorId = $parameters[0];
            $date = \Carbon\Carbon::parse($value);
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

            $doctor = \App\Models\Doctor::find($doctorId);
            if (!$doctor) {
                return false;
            }

            $horaires = $doctor->horaires ?? [];
            return isset($horaires[$frenchDay]) && !empty($horaires[$frenchDay]);
        }, 'The doctor is not available on the selected day.');
    }
}
