<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Get the authenticated doctor's profile.
     */
    public function show(Request $request)
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
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'telephone' => ['nullable', 'string', 'max:20'],
            'adresse' => ['nullable', 'string'],
            'sexe' => ['nullable', 'in:homme,femme'],
            'date_de_naissance' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'speciality_id' => ['required', 'exists:specialities,id'],
            'horaires' => ['nullable', 'json'],
        ]);

        // Update user data
        $user->update([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'adresse' => $request->adresse,
            'sexe' => $request->sexe,
            'date_de_naissance' => $request->date_de_naissance,
        ]);

        // Update doctor specific data
        $user->doctor()->update([
            'speciality_id' => $request->speciality_id,
            'description' => $request->description,
            'horaires' => $request->horaires,
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
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Update profile photo.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        $user = $request->user();
        
        if ($user->photo) {
            // Delete previous photo
            if (file_exists(public_path('storage/' . $user->photo))) {
                unlink(public_path('storage/' . $user->photo));
            }
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

    public function completeProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Validate the user is a doctor
            if ($user->role !== 'medecin') {
                return response()->json(['message' => 'Unauthorized. User is not a doctor'], 403);
            }
            
            // Validate request
            $validated = $request->validate([
                'speciality_id' => ['required', 'exists:specialities,id'],
                'telephone' => ['nullable', 'string', 'max:20'],
                'adresse' => ['nullable', 'string', 'max:255'],
                'sexe' => ['nullable', 'string', 'in:homme,femme'],
                'date_de_naissance' => ['nullable', 'date', 'before:today'],
            ]);
            
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
