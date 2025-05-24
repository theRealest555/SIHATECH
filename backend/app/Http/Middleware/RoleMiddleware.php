<?php
// app/Http/Middleware/RoleMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        Log::info('RoleMiddleware Check', [
            'user_id' => $user ? $user->id : null,
            'user_role' => $user ? $user->role : 'no role',
            'required_role' => $role,
            'user_status' => $user ? $user->status : 'no status',
        ]);

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.'
            ], 401);
        }

        if ($user->role !== $role) {
            return response()->json([
                'message' => "Access denied. Required role: {$role}, your role: {$user->role}"
            ], 403);
        }

        return $next($request);
    }
}
