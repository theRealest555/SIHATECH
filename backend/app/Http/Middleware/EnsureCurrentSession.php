<?php

namespace App\Http\Middleware;

use App\Services\AccountCredentials;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        if (! $request->hasSession() || ! $guard->check()) {
            return $next($request);
        }
        $user = $guard->user();
        $marker = $request->session()->get(AccountCredentials::SESSION_KEY);
        // Existing sessions may bootstrap only until the first security change.
        $current = $marker === null ? $user->auth_version === 0 : (
            is_array($marker) && ($marker['user_id'] ?? null) === $user->id
            && ($marker['version'] ?? null) === $user->auth_version
        );
        if (! $current) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw new AuthenticationException('Your session has expired. Please sign in again.', ['web', 'sanctum']);
        }
        if ($marker === null) {
            app(AccountCredentials::class)->stampSession($request, $user);
        }

        return $next($request);
    }
}
