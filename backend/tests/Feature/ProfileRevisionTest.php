<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): array
    {
        $patient = Patient::factory()->create();
        $user = $patient->user;
        Sanctum::actingAs($user);
        Notification::fake();

        return [$user, $patient, ['nom' => $user->nom, 'prenom' => $user->prenom, 'email' => $user->email, 'expected_profile_revision' => $this->getJson('/api/patient/profile')->assertOk()->json('profile_revision')]];
    }

    private function doctor(): array
    {
        $doctor = Doctor::factory()->create(['is_verified' => true, 'horaires' => ['lundi' => ['09:00-12:00']]]);
        $user = $doctor->user;
        Sanctum::actingAs($user);

        return [$user, $doctor, ['nom' => $user->nom, 'prenom' => $user->prenom, 'email' => $user->email, 'speciality_id' => $doctor->speciality_id, 'expected_profile_revision' => $this->getJson('/api/doctor/profile')->assertOk()->json('profile_revision')]];
    }

    public function test_patient_stale_edits_preserve_newer_contact_and_favorite_data(): void
    {
        [$user, $patient, $old] = $this->patient();
        $doctor = Doctor::factory()->create();
        $new = $this->putJson('/api/patient/profile', array_replace($old, ['telephone' => '0612345678', 'medecin_favori_id' => $doctor->id]))->assertOk()->json('profile_revision');
        $this->assertNotSame($old['expected_profile_revision'], $new);
        $this->putJson('/api/patient/profile', array_replace($old, ['telephone' => '0600000000', 'medecin_favori_id' => null]))->assertConflict();
        $this->assertSame('0612345678', $user->fresh()->telephone);
        $this->assertSame($doctor->id, $patient->fresh()->medecin_favori_id);
        Notification::assertNothingSent();
    }

    public function test_schedule_edits_invalidate_old_profile_snapshots_and_schedule_snapshots(): void
    {
        [$user, $doctor, $profile] = $this->doctor();
        $revision = $this->getJson('/api/doctor/availability')->assertOk()->json('data.schedule_revision');
        $this->putJson('/api/doctor/schedule', ['schedule' => ['lundi' => ['10:00-12:00']], 'expected_schedule_revision' => $revision])->assertOk();
        $this->putJson('/api/doctor/schedule', ['schedule' => [], 'expected_schedule_revision' => $revision])->assertConflict();
        $this->putJson('/api/doctor/profile', $profile + ['horaires' => ['lundi' => ['09:00-12:00']]])->assertConflict();
        $this->assertSame(['lundi' => ['10:00-12:00']], $doctor->fresh()->horaires);
    }

    public function test_profile_schedule_changes_invalidate_availability_but_metadata_edits_do_not(): void
    {
        [$user, $doctor, $profile] = $this->doctor();
        $revision = $this->getJson('/api/doctor/availability')->json('data.schedule_revision');
        $profile['expected_profile_revision'] = $this->putJson('/api/doctor/profile', $profile + ['description' => 'Updated practice'])->assertOk()->json('profile_revision');
        $this->putJson('/api/doctor/schedule', ['schedule' => ['lundi' => ['09:00-12:00']], 'expected_schedule_revision' => $revision])->assertOk();
        $this->putJson('/api/doctor/profile', $profile + ['horaires' => ['lundi' => ['11:00-12:00']]])->assertOk();
        $this->putJson('/api/doctor/schedule', ['schedule' => [], 'expected_schedule_revision' => $revision])->assertConflict();
    }

    public function test_missing_or_foreign_revisions_cannot_bypass_the_guard(): void
    {
        [$user, $patient, $data] = $this->patient();
        $missing = $data;
        unset($missing['expected_profile_revision']);
        $this->putJson('/api/patient/profile', $missing)->assertUnprocessable();
        $other = Patient::factory()->create();
        Sanctum::actingAs($other->user);
        $this->putJson('/api/patient/profile', ['nom' => 'Changed', 'prenom' => $other->user->prenom, 'email' => $other->user->email, 'expected_profile_revision' => $data['expected_profile_revision']])->assertConflict();
        $this->assertNotSame('Changed', $other->user->fresh()->nom);
    }

    public function test_photo_and_verification_refresh_do_not_invalidate_contact_edits(): void
    {
        [$user, $patient, $data] = $this->patient();
        $user->update(['photo' => 'users/another.png', 'email_verified_at' => now()->subMinute()]);
        $this->putJson('/api/patient/profile', $data + ['telephone' => '0611111111'])->assertOk();
        $this->assertSame('users/another.png', $user->fresh()->photo);
    }

    public function test_onboarding_cannot_overwrite_a_newer_profile_edit(): void
    {
        [$user, $doctor, $data] = $this->doctor();
        $this->putJson('/api/doctor/profile', $data + ['adresse' => 'New clinic'])->assertOk();
        $this->postJson('/api/doctor/complete-profile', ['speciality_id' => $doctor->speciality_id, 'adresse' => 'Old clinic', 'expected_profile_revision' => $data['expected_profile_revision']])->assertConflict();
        $this->assertSame('New clinic', $user->fresh()->adresse);
    }
}
