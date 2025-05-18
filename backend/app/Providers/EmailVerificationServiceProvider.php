<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use App\Notifications\VerifyEmailNotification;

class EmailVerificationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure custom URL generation for email verification
        VerifyEmailNotification::createUrlUsing(function ($notifiable) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $apiUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
            
            // Extract the verification parameters from the URL to recreate it for frontend
            $parts = parse_url($apiUrl);
            parse_str($parts['query'], $query);
            
            // Build the frontend URL with the verification parameters
            return $apiUrl;
        });
    }
}