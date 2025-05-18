<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Get the authenticated user, checking both web session and API token
        $user = $request->user() ?: auth()->guard('sanctum')->user();
        
        // Log for debugging purposes
        Log::info('RoleMiddleware Check', [
            'user' => $user ? $user->toArray() : null,
            'role' => $role,
            'user_role' => $user ? $user->role : 'no role',
            'token' => $request->bearerToken(),
        ]);

        if (!$user || $user->role !== $role) {
            return response()->json([
                'message' => 'Unauthorized. Access denied.'
            ], 403);
        }

        return $next($request);
    }
}