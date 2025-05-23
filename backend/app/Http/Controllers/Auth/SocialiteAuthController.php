<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Patient;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class SocialiteAuthController extends Controller
{
    /**
     * Redirect to social provider
     */
    public function redirect(string $provider)
    {
        try {
            // Validate provider
            if (!in_array($provider, ['google', 'facebook'])) {
                return redirect('http://localhost:3000/login?error=unsupported_provider');
            }

            // Redirect to the social provider
            return Socialite::driver($provider)->redirect();

        } catch (\Exception $e) {
            Log::error('Social redirect error: ' . $e->getMessage());
            return redirect('http://localhost:3000/login?error=redirect_failed');
        }
    }

    /**
     * Handle provider callback
     */
    public function callback(string $provider)
    {
        try {
            // Validate provider
            if (!in_array($provider, ['google', 'facebook'])) {
                return redirect('http://localhost:3000/login?error=unsupported_provider');
            }

            // Get social user data
            $socialUser = Socialite::driver($provider)->user();

            // Find existing user by email first
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                // Update existing user with provider details if not already set
                if (!$user->provider_id || !$user->provider) {
                    $user->update([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ]);
                }
            } else {
                // Create new user
                $name = $socialUser->getName();
                $nameParts = explode(' ', $name, 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';

                // Create the user
                $user = User::create([
                    'nom' => $lastName,
                    'prenom' => $firstName,
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(Str::random(16)), // Random password
                    'role' => 'patient', // Default to patient
                    'status' => 'actif',
                    'email_verified_at' => now(), // Email is verified through social provider
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'photo' => $socialUser->getAvatar(),
                    'telephone' => '', // Will be filled later if needed
                ]);

                // Create patient profile by default
                Patient::create([
                    'user_id' => $user->id,
                    'date_naissance' => null, // To be filled later
                    'adresse' => '', // To be filled later
                    'numero_assurance' => '', // To be filled later
                ]);
            }

            // Generate token
            $token = $user->createToken('social-auth-token', [$user->role])->plainTextToken;

            // Prepare user data for frontend
            $userData = [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'role' => $user->role,
                'photo' => $user->photo,
                'email_verified_at' => $user->email_verified_at,
            ];

            // Redirect to frontend with token and user data
            $frontendUrl = 'http://localhost:3000/auth/callback';
            $queryParams = http_build_query([
                'token' => $token,
                'user' => json_encode($userData),
                'provider' => $provider,
            ]);

            return redirect($frontendUrl . '?' . $queryParams);
        } catch (\Exception $e) {
            Log::error('Social callback error: ' . $e->getMessage());
            return redirect('http://localhost:3000/login?error=authentication_failed&message=' . urlencode($e->getMessage()));
        }
    }
}
