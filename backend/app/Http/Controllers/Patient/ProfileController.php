<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\UpdatePasswordRequest;
use App\Http\Requests\Patient\UpdateProfileRequest;
use App\Models\User;
use App\Services\AccountCredentials;
use App\Services\ProfileEmail;
use App\Services\ProfilePhoto;
use App\Services\ProfileRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * Get the authenticated patient's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();
        $patient = $user->patient()->with('medecinFavori.user')->first();

        return response()->json([
            'user' => $user,
            'patient' => $patient,
            'profile_revision' => app(ProfileRevision::class)->profile($user, patient: $patient),
        ]);
    }

    /**
     * Update the patient's profile information.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        [$user, $emailChanged, $patient] = DB::transaction(function () use ($request, $validated) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $patient = $user->patient()->lockForUpdate()->firstOrFail();
            app(ProfileRevision::class)->assertCurrent($validated['expected_profile_revision'], app(ProfileRevision::class)->profile($user, patient: $patient), 'profile');
            $emailChanged = app(ProfileEmail::class)->prepare($user, $validated);
            $user->fill(array_intersect_key($validated, array_flip([
                'nom', 'prenom', 'email', 'telephone', 'adresse', 'sexe', 'date_de_naissance',
            ])))->save();
            if (array_key_exists('medecin_favori_id', $validated)) {
                $user->patient()->update(['medecin_favori_id' => $validated['medecin_favori_id']]);
            }

            return [$user->fresh(), $emailChanged, $patient->fresh()->load('medecinFavori.user')];
        }, 3);
        $sent = $emailChanged ? app(ProfileEmail::class)->notify($user) : false;

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
            'patient' => $patient,
            'profile_revision' => app(ProfileRevision::class)->profile($user, patient: $patient),
            'email_verification_required' => $emailChanged,
            'email_verification_sent' => $sent,
        ]);
    }

    /**
     * Update the patient's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $credentials = app(AccountCredentials::class);
        $user = $credentials->changePassword($request->user()->id, $validated['password'], $validated['current_password']);
        $keptSession = $credentials->preserveCurrentSession($request, $user);

        return response()->json([
            'message' => 'Password updated successfully',
            'other_sessions_revoked' => true,
            'api_tokens_revoked' => true,
            'reauthentication_required' => ! $keptSession,
        ]);
    }

    /**
     * Update profile photo.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $user = app(ProfilePhoto::class)->replace($request->user()->id, $request->file('photo'), 'users');

        return response()->json([
            'message' => 'Photo updated successfully',
            'user' => $user,
            'photo_url' => url('storage/'.$user->photo),
            'path' => $user->photo,
        ]);
    }
}
