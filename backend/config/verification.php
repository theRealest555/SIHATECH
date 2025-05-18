<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Verification Settings
    |--------------------------------------------------------------------------
    |
    | Here you may configure your application's email verification settings.
    | This includes verification link expiration and frontend redirect URLs.
    |
    */

    // How long should verification links be valid for (in minutes)
    'expire' => 60,

    // Frontend URLs for redirection after verification
    'redirect' => [
        'success' => env('FRONTEND_URL', 'http://localhost:3000') . '/dashboard?verified=1',
        'already_verified' => env('FRONTEND_URL', 'http://localhost:3000') . '/dashboard?verified=1',
        'error' => env('FRONTEND_URL', 'http://localhost:3000') . '/email/verify/error',
    ],
];