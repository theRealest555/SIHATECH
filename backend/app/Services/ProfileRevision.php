<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;

class ProfileRevision
{
    private function token(array $data): string
    {
        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function profile(User $user, ?Doctor $doctor = null, ?Patient $patient = null): string
    {
        return $this->token([
            'user_id' => $user->id,
            'user' => $user->only(['nom', 'prenom', 'email', 'telephone', 'adresse', 'sexe', 'date_de_naissance']),
            'doctor' => $doctor?->only(['id', 'speciality_id', 'description', 'horaires']),
            'patient' => $patient?->only(['id', 'medecin_favori_id']),
        ]);
    }

    public function schedule(Doctor $doctor): string
    {
        return $this->token(['doctor_id' => $doctor->id, 'schedule' => $doctor->horaires ?? []]);
    }

    // Caller holds all rows represented by the token locked until commit.
    public function assertCurrent(string $expected, string $current, string $subject): void
    {
        abort_unless(hash_equals($current, $expected), 409, "This {$subject} changed in another session. Reload the latest data and review your changes before saving.");
    }
}
