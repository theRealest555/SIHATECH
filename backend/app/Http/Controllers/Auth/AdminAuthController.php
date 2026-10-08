<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Admin;
use App\Models\User;
use App\Services\AccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Handle an admin login request.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->email)
                ->where('role', 'admin')
                ->first();

            $request->ensureIsNotRateLimited();
            if (! $user || ! Hash::check($request->password, $user->password) || ! $user->isApprovedAdmin()) {
                RateLimiter::hit($request->throttleKey());
                throw ValidationException::withMessages([
                    'email' => ['Les informations d\'identification fournies sont incorrectes.'],
                ]);
            }

            RateLimiter::clear($request->throttleKey());
            Auth::guard('web')->login($user, $request->boolean('remember'));
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }
            $token = null;
            if (! $request->hasSession()) {
                [$user, $token] = app(AccountCredentials::class)->issueToken($user->id, $request->input('password'), 'admin-token');
            }

            return response()->json([
                'user' => $user,
                'token' => $token,
            ], 200);
        } catch (ValidationException $e) {
            Auth::guard('web')->logoutCurrentDevice();

            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
            ], 500);
        }
    }

    /**
     * Logout the admin.
     */
    public function logout(Request $request): JsonResponse
    {
        return app(AuthenticatedSessionController::class)->destroy($request);
    }
}
