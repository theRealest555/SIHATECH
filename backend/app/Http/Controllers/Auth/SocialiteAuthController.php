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

class SocialiteAuthController extends Controller
{
    /**
     * Redirect to social provider
     */
    public function redirect(Request $request, string $provider): JsonResponse
    {
        // Validate provider
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json(['message' => 'Unsupported provider'], 400);
        }
        
        // Validate role
        $role = $request->input('role', 'patient');
        if (!in_array($role, ['patient', 'medecin'])) {
            return response()->json(['message' => 'Invalid role'], 400);
        }
        
        // Generate a state parameter with role information
        $state = base64_encode(json_encode([
            'role' => $role,
            'nonce' => Str::random(40)
        ]));
        
        // Get the redirect URL with state parameter
        $redirectUrl = Socialite::driver($provider)->redirect()->with(['state' => $state])->getTargetUrl();
        
        return response()->json(['redirect_url' => $redirectUrl]);
    }
    
    /**
     * Handle provider callback
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        try {
            // Get social user data
            $socialUser = Socialite::driver($provider)->user();
            
            // Extract role from state parameter
            $role = 'patient'; // Default
            if ($request->has('state')) {
                try {
                    $stateData = json_decode(base64_decode($request->state), true);
                    if (isset($stateData['role']) && in_array($stateData['role'], ['patient', 'medecin'])) {
                        $role = $stateData['role'];
                    }
                } catch (\Exception $e) {
                    // State parsing failed, use default role
                }
            }
            
            // Find existing user by provider id and provider
            $user = User::where('provider_id', $socialUser->getId())
                        ->where('provider', $provider)
                        ->first();
            
            // If user doesn't exist but email exists, link accounts
            if (!$user && $existingUser = User::where('email', $socialUser->getEmail())->first()) {
                // Update existing user with provider details
                $existingUser->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                ]);
                $user = $existingUser;
            }
            
            // If no user found, create new user
            if (!$user) {
                // Extract name components
                $name = $socialUser->getName();
                $nameParts = explode(' ', $name);
                $firstName = $nameParts[0] ?? '';
                $lastName = count($nameParts) > 1 ? end($nameParts) : '';
                
                // Create the user
                $user = User::create([
                    'nom' => $lastName,
                    'prenom' => $firstName,
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(Str::random(16)), // Random password
                    'role' => $role,
                    'status' => 'actif',
                    'email_verified_at' => now(), // Email is already verified through social provider
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'photo' => $socialUser->getAvatar(),
                ]);
                
                // Create role-specific profile
                if ($role === 'patient') {
                    Patient::create([
                        'user_id' => $user->id,
                    ]);
                } elseif ($role === 'medecin') {
                    // For doctors, they'll need to complete their profile after registration
                    Doctor::create([
                        'user_id' => $user->id,
                        'is_verified' => false, // Require verification for doctors
                    ]);
                }
            }
            
            // Generate token with appropriate ability based on user role
            $token = $user->createToken('social-auth-token', [$user->role])->plainTextToken;
            
            // Return user and token
            return response()->json([
                'user' => $user,
                'role' => $user->role,
                'token' => $token,
                'requires_profile_completion' => $user->role === 'medecin' && 
                    ($user->doctor && empty($user->doctor->speciality_id)),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}