<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifiedDoctor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'medecin') {
            return response()->json([
                'message' => 'Unauthorized. Access denied.'
            ], 403);
        }
        
        if (!$user->doctor || !$user->doctor->is_verified) {
            return response()->json([
                'message' => 'Your account is not verified yet. Please complete your profile and submit required documents.'
            ], 403);
        }

        return $next($request);
    }
}
