<?php

// use Illuminate\Foundation\Application;
// use Illuminate\Foundation\Configuration\Exceptions;
// use Illuminate\Foundation\Configuration\Middleware;
// use Illuminate\Auth\AuthenticationException;
// use Illuminate\Http\Request;

// return Application::configure(basePath: dirname(__DIR__))
//     ->withRouting(
//         web: __DIR__.'/../routes/web.php',
//         api: __DIR__.'/../routes/api.php',
//         commands: __DIR__.'/../routes/console.php',
//         health: '/up',
//     )
//     ->withMiddleware(function (Middleware $middleware) {
//         $middleware->api(prepend: [
//             \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
//             \Illuminate\Routing\Middleware\SubstituteBindings::class,
//         ]);

//         $middleware->alias([
//             'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
//             'auth:sanctum' => \Illuminate\Auth\Middleware\Authenticate::class.':sanctum',
//             'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
//             'role' => \App\Http\Middleware\RoleMiddleware::class,
//             'verified.doctor' => \App\Http\Middleware\VerifiedDoctor::class,
//             'active.user' => \App\Http\Middleware\ActiveUser::class,
//         ]);
//     })
//     ->withExceptions(function (Exceptions $exceptions) {
//         $exceptions->render(function (AuthenticationException $e, Request $request) {
//             if ($request->expectsJson()) {
//                 return response()->json([
//                     'message' => 'you should login',
//                 ], 401);
//             }
//             return response()->redirectTo('/login'); // Fallback for non-API requests
//         });
//     })->create();


use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class.':sanctum',
            'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class.':sanctum',
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'verified.doctor' => \App\Http\Middleware\VerifiedDoctor::class,
            'active.user' => \App\Http\Middleware\ActiveUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'you should login',
                ], 401);
            }
            return response()->redirectTo('/login'); // Fallback for non-API requests
        });
    })->create();