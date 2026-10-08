<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountCredentials
{
    public const SESSION_KEY = 'account_authentication';

    // The caller must hold the user's row lock within its transaction.
    public function revoke(User $user): void
    {
        $user->forceFill([
            'auth_version' => $user->auth_version + 1,
            'remember_token' => Str::random(60),
        ])->save();
        $user->tokens()->delete();
    }

    public function changePassword(int $userId, string $password, ?string $currentPassword = null, ?string $expectedEmail = null): User
    {
        return DB::transaction(function () use ($userId, $password, $currentPassword, $expectedEmail) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            if ($expectedEmail !== null && $user->email !== $expectedEmail) {
                throw ValidationException::withMessages(['email' => 'The reset link no longer matches this account.']);
            }
            if ($currentPassword !== null && ! Hash::check($currentPassword, $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            $user->password = Hash::make($password);
            $this->revoke($user);
            // Older recovery links must not overwrite credentials changed through another flow.
            Password::deleteToken($user);

            return $user;
        }, 3);
    }

    public function issueToken(int $userId, string $password, string $name): array
    {
        return DB::transaction(function () use ($userId, $password, $name) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            // Login may have read these credentials before a concurrent reset or suspension.
            if (! Hash::check($password, $user->password) || ! $user->isActive()
                || ($user->isAdmin() && ! $user->isApprovedAdmin())) {
                throw ValidationException::withMessages(['email' => 'These credentials are no longer valid. Please sign in again.']);
            }

            return [$user, $user->createToken($name, [$user->role])->plainTextToken];
        }, 3);
    }

    public function stampSession(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, ['user_id' => $user->id, 'version' => $user->auth_version]);
    }

    public function preserveCurrentSession(Request $request, User $user): bool
    {
        $guard = Auth::guard('web');
        if (! $request->hasSession() || ! $guard->check() || $guard->id() !== $user->id) {
            return false;
        }
        $guard->setUser($user);
        $this->stampSession($request, $user);
        $request->session()->regenerate();

        return true;
    }
}
