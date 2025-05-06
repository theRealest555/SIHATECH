<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Get the authenticated patient's profile.
     */
    public function show(Request $request)
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
            'medecin_favori_id' => ['nullable', 'exists:doctors,id'],
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

        // Update favorite doctor if provided
        if ($request->has('medecin_favori_id')) {
            $user->patient()->update([
                'medecin_favori_id' => $request->medecin_favori_id
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
    try{
    $request->validate([
        'photo' => ['required', 'image', 'max:5120'], // 5MB max
    ]);

    $user = $request->user();
    
    // First store the new photo to ensure successful upload before deleting the old one
    $path = $request->file('photo')->store('users', 'public');
    
    // Now handle the old photo deletion if needed
    if ($user->photo) {
        // Delete previous photo - using Storage facade for better handling
        $oldPhotoPath = public_path('storage/' . $user->photo);
        if (file_exists($oldPhotoPath)) {
            unlink($oldPhotoPath);
        }
    }
    
    // Update user record with new photo path
    $user->update([
        'photo' => $path
    ]);

    return response()->json([
        'message' => 'Photo updated successfully',
        'photo_url' => url('storage/' . $path),
        'path' => $path
    ]);} catch (\Exception $e) {
        return response()->json([
            'message' => 'Error updating photo: ' . $e->getMessage(),
        ], 500);
    }
    }
}