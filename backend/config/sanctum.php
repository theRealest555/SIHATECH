<?php

use Laravel\Sanctum\Sanctum;

$frontendUrl = env('FRONTEND_URL');
$frontendUrlAppend = '';
if ($frontendUrl) {
    $parsedUrl = parse_url($frontendUrl);
    $host = $parsedUrl['host'] ?? '';
    $port = $parsedUrl['port'] ?? null;
    $frontendUrlAppend = ',' . $host . ($port ? ':' . $port : '');
}

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
        '%s%s%s',
        'localhost,localhost:3000,localhost:3001,127.0.0.1,127.0.0.1:3000,127.0.0.1:3001,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        $frontendUrlAppend
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s%s',
        'localhost,localhost:3000,localhost:3001,127.0.0.1,127.0.0.1:3000,127.0.0.1:3001,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        $frontendUrlAppend
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token
    | itself. If set to null, token expiration is handled by the token.
    |
    */

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 60 * 24 * 7), // 7 days

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens to prevent collisions if multiple Sanctum
    | using applications exist within a single application. You may change
    | the token prefix from the default.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
