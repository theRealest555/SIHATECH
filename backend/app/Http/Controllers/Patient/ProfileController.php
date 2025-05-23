<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Patient;
use App\Http\Requests\Patient\UpdateProfileRequest;
use App\Http\Requests\Patient\UpdatePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get the authenticated patient's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = $user->patient()->with('medecinFavori.user')->first();

        return response()->json([
            'user' => $user,
            'patient' => $patient,
        ]);
    }

    /**
     * Update the patient's profile information.
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

        // Update favorite doctor if provided
        if (isset($validated['medecin_favori_id'])) {
            $user->patient()->update([
                'medecin_favori_id' => $validated['medecin_favori_id']
            ]);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
            'patient' => $user->patient()->with('medecinFavori.user')->first(),
        ]);
    }

    /**
     * Update the patient's password.
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
        try {
            $request->validate([
                'photo' => ['required', 'image', 'max:5120'], // 5MB max
            ]);

            $user = $request->user();

            // First store the new photo
            $path = $request->file('photo')->store('users', 'public');

            // Now handle the old photo deletion if needed
            if ($user->photo) {
                // Delete previous photo using Storage facade
                Storage::disk('public')->delete($user->photo);
            }

            // Update user record with new photo path
            $user->update([
                'photo' => $path
            ]);

            return response()->json([
                'message' => 'Photo updated successfully',
                'photo_url' => url('storage/' . $path),
                'path' => $path
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating photo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
