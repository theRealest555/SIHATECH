<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->guard('sanctum')->user();

        Log::info('ActiveUser Middleware Check', [
            'user' => $user ? $user->toArray() : null,
            'status' => $user ? $user->status : 'no user',
            'token' => $request->bearerToken(),
        ]);

        if (!$user) {
            return response()->json([
                'message' => 'you should login',
            ], 401);
        }

        if ($user->status !== 'actif') {
            return response()->json([
                'message' => 'Your account is currently ' . $user->status . '. Please contact the administrator.'
            ], 403);
        }

        return $next($request);
    }
}