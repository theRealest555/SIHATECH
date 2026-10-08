<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ProfileEmail
{
    public function prepare(User $user, array $validated): bool
    {
        if ($validated['email'] === $user->email) {
            return false;
        }

        if (! Hash::check($validated['current_password'] ?? '', $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Confirm your current password to change your email. If you signed in with a provider, first set a password using password reset.',
            ]);
        }

        // Callers hold the account lock inside the profile transaction. Recovery tokens
        // are keyed by email, so discard tokens at both sides of the address change.
        Password::deleteToken($user);
        $destination = clone $user;
        $destination->forceFill(['email' => $validated['email']]);
        Password::deleteToken($destination);
        $user->forceFill(['email_verified_at' => null]);

        return true;
    }

    public function notify(User $user): bool
    {
        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            // The change is already committed. The verification page permits resending.
            return false;
        }
    }
}
