<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

function renderAuthException(\Illuminate\Auth\AuthenticationException $e, Request $request) {
    if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json([
            'message' => 'Unauthenticated. Please login.',
        ], 401);
    }
    return response()->json(['message' => 'Unauthenticated'], 401);
}

function renderValidationException(\Illuminate\Validation\ValidationException $e, Request $request) {
    if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }
    throw $e;
}

function renderGeneralException(\Throwable $e, Request $request) {
    if ($request->expectsJson() || $request->is('api/*')) {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        return response()->json([
            'message' => $e->getMessage() ?: 'Server Error',
            'error' => config('app.debug') ? [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace()
            ] : null
        ], $status);
    }
    throw $e;
}


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API middleware stack with Sanctum
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Define middleware aliases
        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,

            // Custom middleware
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'verified.doctor' => \App\Http\Middleware\VerifiedDoctor::class,
            'active.user' => \App\Http\Middleware\ActiveUser::class,
        ]);

        // Enable rate limiting for all API routes
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle authentication exceptions for API
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated. Please login.',
                ], 401);
            }
            // Fallback for non-API requests (though we shouldn't have any)
            return response()->json(['message' => 'Unauthenticated'], 401);
        });

        // Handle validation exceptions
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        });

        // Handle general exceptions for API
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
                return response()->json([
                    'message' => $e->getMessage() ?: 'Server Error',
                    'error' => config('app.debug') ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTrace()
                    ] : null
                ], $status);
            }
            throw $e;
        });
    })->create();
