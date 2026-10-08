<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            // Authorization may have read the identity before a concurrent email change.
            abort_unless(hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash')), 403);
            if ($user->hasVerifiedEmail()) {
                return redirect()->intended(config('verification.redirect.already_verified'));
            }
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            return redirect()->intended(config('verification.redirect.success'));
        }, 3);
    }

    /**
     * Handle verification errors.
     */
    public function error(Request $request): RedirectResponse
    {
        return redirect()->to(config('verification.redirect.error'));
    }
}
