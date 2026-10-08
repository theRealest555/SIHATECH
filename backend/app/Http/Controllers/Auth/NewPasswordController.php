<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountCredentials;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024', 'confirmed', Rules\Password::defaults()],
        ]);

        $resetUser = null;
        $status = DB::transaction(function () use ($request, &$resetUser) {
            // Serialize competing reset attempts before the broker validates the token.
            $lockedUser = User::where('email', $request->input('email'))->lockForUpdate()->first();
            if (! $lockedUser) {
                return Password::INVALID_TOKEN;
            }

            return Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user) use ($request, $lockedUser, &$resetUser) {
                    if ($user->id !== $lockedUser->id) {
                        throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired. Request a new link.']);
                    }
                    $resetUser = app(AccountCredentials::class)->changePassword($user->id, (string) $request->string('password'), expectedEmail: $user->email);
                }
            );
        }, 3);

        if ($status != Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['This reset link is invalid or expired. Request a new link.'],
            ]);
        }

        event(new PasswordReset($resetUser));

        return response()->json(['status' => __($status), 'message' => 'Password reset successfully. Sign in with your new password.'], 200, ['Cache-Control' => 'no-store']);
    }
}
