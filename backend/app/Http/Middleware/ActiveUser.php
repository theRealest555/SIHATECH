<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || $user->status !== 'actif') {
            // Logout the user
            if ($user) {
                $user->tokens()->delete();
            }
            
            return response()->json([
                'message' => 'Your account is currently ' . ($user ? $user->status : 'inactive') . '. Please contact the administrator.'
            ], 403);
        }

        return $next($request);
    }
}
