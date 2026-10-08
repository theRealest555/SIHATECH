<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->user()->id);
        if (! $user->isActive() || ($user->isAdmin() && ! $user->isApprovedAdmin())) {
            return response()->json(['message' => 'This account is unavailable. Contact support.'], 403, ['Cache-Control' => 'no-store']);
        }
        if ($user->hasVerifiedEmail()) {
            return response()->json(['status' => 'already-verified'], 200, ['Cache-Control' => 'no-store']);
        }
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('Verification email delivery failed', ['exception' => get_class($exception)]);

            return response()->json(['message' => 'Unable to send verification email. Please try again later.'], 503, ['Cache-Control' => 'no-store']);
        }

        return response()->json(['status' => 'verification-link-sent'], 200, ['Cache-Control' => 'no-store']);
    }
}
