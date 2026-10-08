<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);

        try {
            // Missing accounts and broker throttling must have the same public response.
            $delivery = DB::transaction(function () use ($request) {
                // Share the account lock used by email changes and password resets.
                $user = User::where('email', $request->input('email'))->lockForUpdate()->first();
                if (! $user) {
                    return null;
                }

                $delivery = null;
                Password::sendResetLink(['id' => $user->id, 'email' => $user->email], function ($recipient, $token) use (&$delivery) {
                    $delivery = [$recipient, $token];
                });

                return $delivery;
            }, 3);

            // Commit the token before sending mail; transport failures never roll back
            // credentials, and a later credential change can invalidate this link.
            if ($delivery !== null) {
                [$user, $token] = $delivery;
                $user->sendPasswordResetNotification($token);
                event(new PasswordResetLinkSent($user));
            }
        } catch (\Throwable $e) {
            // Operator visibility without exposing the account or token.
            Log::warning('Password reset delivery failed', ['exception' => get_class($e)]);
        }
        $message = 'If an account matches this email, a password reset link will be sent. Check your inbox and spam folder.';

        return response()->json(['status' => $message, 'message' => $message], 200, ['Cache-Control' => 'no-store']);
    }
}
