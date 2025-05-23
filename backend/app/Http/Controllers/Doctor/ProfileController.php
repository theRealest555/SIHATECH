<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Doctor;
use App\Http\Requests\Doctor\UpdateProfileRequest;
use App\Http\Requests\Doctor\UpdatePasswordRequest;
use App\Http\Requests\Doctor\CompleteProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Get the authenticated doctor's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $doctor = $user->doctor()->with(['speciality', 'documents'])->first();

        return response()->json([
            'user' => $user,
            'doctor' => $doctor,
        ]);
    }

    /**
     * Update the doctor's profile information.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Update user data
        $user->update([
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'email' => $validated['email'],
            'telephone' => $validated['telephone'],
            'adresse' => $validated['adresse'],
            'sexe' => $validated['sexe'],
            'date_de_naissance' => $validated['date_de_naissance'],
        ]);

        // Update doctor specific data
        $user->doctor()->update([
            'speciality_id' => $validated['speciality_id'],
            'description' => $validated['description'],
            'horaires' => $validated['horaires'],
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
            'doctor' => $user->doctor()->with(['speciality', 'documents'])->first(),
        ]);
    }

    /**
     * Update the doctor's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Update profile photo.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        $user = $request->user();

        if ($user->photo) {
            // Delete previous photo if it exists
            Storage::disk('public')->delete($user->photo);
        }

        $path = $request->file('photo')->store('doctors', 'public');

        $user->update([
            'photo' => $path
        ]);

        return response()->json([
            'message' => 'Photo updated successfully',
            'photo_url' => url('storage/' . $path),
        ]);
    }

    /**
     * Complete profile after social registration.
     */
    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            // Update user info
            $user->update([
                'telephone' => $validated['telephone'] ?? $user->telephone,
                'adresse' => $validated['adresse'] ?? $user->adresse,
                'sexe' => $validated['sexe'] ?? $user->sexe,
                'date_de_naissance' => $validated['date_de_naissance'] ?? $user->date_de_naissance,
            ]);

            // Update doctor's speciality
            if ($user->doctor) {
                $user->doctor()->update([
                    'speciality_id' => $validated['speciality_id'],
                ]);
            }

            return response()->json([
                'message' => 'Profile completed successfully',
                'user' => $user->fresh(['doctor']),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
