<?php

use Illuminate\Support\Facades\Route;

// Health check route
Route::get('/', function () {
    return response()->json([
        'message' => 'Laravel API is running',
        'version' => app()->version(),
        'status' => 'OK'
    ]);
});

// Additional health/status endpoints
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'services' => [
            'database' => 'OK', // You can add actual health checks here
            'cache' => 'OK',
        ]
    ]);
});

// Sanctum CSRF cookie endpoint for SPA authentication
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});
