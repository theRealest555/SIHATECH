<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        // If user is already verified, redirect to success URL
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(config('verification.redirect.already_verified'));
        }

        // Mark email as verified and fire the Verified event
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        // Redirect to the success URL
        return redirect()->intended(config('verification.redirect.success'));
    }

    /**
     * Handle verification errors.
     */
    public function error(Request $request): RedirectResponse
    {
        return redirect()->to(config('verification.redirect.error'));
    }
}