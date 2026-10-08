<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\CompleteProfileRequest;
use App\Http\Requests\Doctor\UpdatePasswordRequest;
use App\Http\Requests\Doctor\UpdateProfileRequest;
use App\Models\Doctor;
use App\Models\User;
use App\Services\AccountCredentials;
use App\Services\DoctorSchedule;
use App\Services\ProfileEmail;
use App\Services\ProfilePhoto;
use App\Services\ProfileRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * Get the authenticated doctor's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();
        $doctor = $user->doctor()->with(['speciality', 'documents'])->first();

        return response()->json([
            'user' => $user,
            'doctor' => $doctor,
            'profile_revision' => app(ProfileRevision::class)->profile($user, $doctor),
        ]);
    }

    /**
     * Update the doctor's profile information.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        [$user, $emailChanged, $doctor] = DB::transaction(function () use ($request) {
            $doctor = Doctor::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();

            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $validated = $request->validated();
            app(ProfileRevision::class)->assertCurrent($validated['expected_profile_revision'], app(ProfileRevision::class)->profile($user, $doctor), 'profile');
            $emailChanged = app(ProfileEmail::class)->prepare($user, $validated);

            $schedule = array_key_exists('horaires', $validated) ? (is_array($validated['horaires']) ? $validated['horaires'] : (json_decode($validated['horaires'] ?? '[]', true) ?? [])) : ($doctor->horaires ?? []);
            app(DoctorSchedule::class)->assertBookingsFit($doctor, $schedule);

            // Update user data
            $user->update([
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'],
                'email' => $validated['email'],
                'telephone' => $validated['telephone'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'sexe' => $validated['sexe'] ?? null,
                'date_de_naissance' => $validated['date_de_naissance'] ?? null,
            ]);

            // Update doctor specific data
            $doctor->update([
                'speciality_id' => $validated['speciality_id'],
                'description' => $validated['description'] ?? null,
                'horaires' => $schedule,
            ]);

            return [$user->fresh(), $emailChanged, $doctor->fresh()->load(['speciality', 'documents'])];
        }, 3);
        $sent = $emailChanged ? app(ProfileEmail::class)->notify($user) : false;

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
            'doctor' => $doctor,
            'profile_revision' => app(ProfileRevision::class)->profile($user, $doctor),
            'email_verification_required' => $emailChanged,
            'email_verification_sent' => $sent,
        ]);
    }

    /**
     * Update the doctor's password.
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
        $user = app(ProfilePhoto::class)->replace($request->user()->id, $request->file('photo'), 'doctors');

        return response()->json([
            'message' => 'Photo updated successfully',
            'user' => $user,
            'photo_url' => url('storage/'.$user->photo),
            'path' => $user->photo,
        ]);
    }

    /**
     * Complete profile after social registration.
     */
    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        [$user, $doctor] = DB::transaction(function () use ($request, $validated) {
            $doctor = Doctor::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            app(ProfileRevision::class)->assertCurrent($validated['expected_profile_revision'], app(ProfileRevision::class)->profile($user, $doctor), 'profile');
            $user->update([
                'telephone' => $validated['telephone'] ?? $user->telephone,
                'adresse' => $validated['adresse'] ?? $user->adresse,
                'sexe' => $validated['sexe'] ?? $user->sexe,
                'date_de_naissance' => $validated['date_de_naissance'] ?? $user->date_de_naissance,
            ]);
            $doctor->update(['speciality_id' => $validated['speciality_id']]);

            return [$user->fresh(['doctor']), $doctor->fresh()];
        }, 3);

        return response()->json([
            'message' => 'Profile completed successfully',
            'user' => $user,
            'profile_revision' => app(ProfileRevision::class)->profile($user, $doctor),
        ]);
    }
}
