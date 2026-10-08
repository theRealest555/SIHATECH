<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Services\SocialProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteAuthController extends Controller
{
    private function failure(string $reason)
    {
        return redirect(config('app.frontend_url').'/login?error='.$reason);
    }

    public function redirect(string $provider)
    {
        if (! in_array($provider, ['google', 'facebook'], true)) {
            return $this->failure('unsupported_provider');
        }
        if (! app(SocialProviders::class)->available($provider)) {
            return $this->failure('provider_unavailable');
        }
        try {
            return Socialite::driver($provider)->redirect();
        } catch (\Throwable $e) {
            Log::warning('Social redirect failed', ['provider' => $provider]);

            return $this->failure('redirect_failed');
        }
    }

    public function callback(Request $request, string $provider)
    {
        if (! in_array($provider, ['google', 'facebook'], true)) {
            return $this->failure('unsupported_provider');
        }
        if ($request->query('error') === 'access_denied') {
            return $this->failure('cancelled');
        }
        if (! app(SocialProviders::class)->available($provider)) {
            return $this->failure('provider_unavailable');
        }
        try {
            // Socialite checks the provider state stored in the web session.
            $identity = Socialite::driver($provider)->user();
            $providerId = $identity->getId();
            if ((! is_string($providerId) && ! is_int($providerId)) || trim((string) $providerId) === '' || strlen((string) $providerId) > 255) {
                return $this->failure('authentication_failed');
            }
            $matches = User::where('provider', $provider)->where('provider_id', (string) $providerId)->limit(2)->get();
            if ($matches->count() > 1) {
                return $this->failure('authentication_failed');
            }
            $user = $matches->first();
            if (! $user) {
                $verified = $provider === 'google' && filter_var($identity->user['email_verified'] ?? $identity->user['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (! $identity->getEmail() || ! $verified) {
                    return $this->failure('verified_email_required');
                }
                // Linking an existing account requires its owner's authorization.
                if (User::where('email', $identity->getEmail())->exists()) {
                    return $this->failure('account_link_required');
                }
                $user = DB::transaction(function () use ($identity, $provider, $providerId) {
                    $parts = explode(' ', $identity->getName() ?? '', 2);
                    $user = User::create([
                        'nom' => $parts[1] ?? '', 'prenom' => $parts[0], 'email' => $identity->getEmail(),
                        'password' => Hash::make(Str::random(40)), 'role' => 'patient', 'status' => 'actif',
                        'provider' => $provider, 'provider_id' => (string) $providerId, 'photo' => $identity->getAvatar(),
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    Patient::create(['user_id' => $user->id]);

                    return $user;
                });
            }
            if ($user->status !== 'actif' || ($user->role === 'admin' && ! $user->isApprovedAdmin())) {
                return $this->failure('account_disabled');
            }
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return redirect(config('app.frontend_url').'/auth/'.$provider.'/callback');
        } catch (InvalidStateException $e) {
            return $this->failure('session_expired');
        } catch (\Throwable $e) {
            Log::warning('Social callback failed', ['provider' => $provider]);

            return $this->failure('authentication_failed');
        }
    }
}
