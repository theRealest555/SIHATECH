<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rendezvous;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function doctor(): Doctor
    {
        $this->travelTo(now()->next('monday')->setTime(8, 0));
        $doctor = Doctor::factory()->create(['is_verified' => true, 'horaires' => ['lundi' => ['09:00-12:00']]]);
        Sanctum::actingAs($doctor->user);

        return $doctor;
    }

    public function test_ranges_are_validated_and_empty_schedule_can_close_unbooked_days(): void
    {
        $doctor = $this->doctor();
        $this->getJson('/api/doctor/availability')->assertOk()
            ->assertJsonPath('data.schedule.lundi.0', '09:00-12:00')
            ->assertJsonPath('data.can_edit', true);
        foreach ([['monday' => ['09:00-12:00']], ['lundi' => ['12:00-09:00']], ['lundi' => ['09:00-10:00', '09:30-11:00']], ['lundi' => ['9:00-12:00']], ['lundi' => ['09:00-09:15']]] as $schedule) {
            $this->putJson('/api/doctor/schedule', ['expected_schedule_revision' => $this->getJson('/api/doctor/availability')->json('data.schedule_revision'), 'schedule' => $schedule])->assertUnprocessable();
            $this->assertSame(['lundi' => ['09:00-12:00']], $doctor->fresh()->horaires);
        }
        $this->putJson('/api/doctor/schedule', ['expected_schedule_revision' => $this->getJson('/api/doctor/availability')->json('data.schedule_revision'), 'schedule' => []])->assertOk();
        $this->assertSame([], $doctor->fresh()->horaires);
    }

    public function test_bookings_must_fit_the_full_slot_and_the_new_grid(): void
    {
        $doctor = $this->doctor();
        $patient = Patient::factory()->create();
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'date_heure' => today()->setTime(9, 30), 'statut' => 'confirmé']);
        foreach (['09:00-09:45', '09:15-12:00'] as $range) {
            $this->putJson('/api/doctor/schedule', ['expected_schedule_revision' => $this->getJson('/api/doctor/availability')->json('data.schedule_revision'), 'schedule' => ['lundi' => [$range]]])->assertConflict();
        }
        $this->putJson('/api/doctor/schedule', ['expected_schedule_revision' => $this->getJson('/api/doctor/availability')->json('data.schedule_revision'), 'schedule' => ['lundi' => ['09:30-12:00']]])->assertOk();
    }

    public function test_profile_schedule_cannot_bypass_booking_protection(): void
    {
        $doctor = $this->doctor();
        $patient = Patient::factory()->create();
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'date_heure' => today()->setTime(9, 30), 'statut' => 'en_attente']);
        $data = ['nom' => 'Changed', 'prenom' => $doctor->user->prenom, 'email' => $doctor->user->email, 'speciality_id' => $doctor->speciality_id,
            'telephone' => null, 'adresse' => null, 'sexe' => null, 'date_de_naissance' => null, 'description' => null,
            'horaires' => json_encode(['lundi' => ['10:00-12:00']])];
        $this->putJson('/api/doctor/profile', array_merge($data, ['expected_profile_revision' => $this->getJson('/api/doctor/profile')->json('profile_revision')]))->assertConflict();
        $this->assertNotSame('Changed', $doctor->user->fresh()->nom);
        $data['horaires'] = json_encode(['lundi' => '09:00-12:00']);
        $this->putJson('/api/doctor/profile', array_merge($data, ['expected_profile_revision' => $this->getJson('/api/doctor/profile')->json('profile_revision')]))->assertUnprocessable();
        $data['horaires'] = ['lundi' => ['09:00-12:00']];
        $this->putJson('/api/doctor/profile', array_merge($data, ['expected_profile_revision' => $this->getJson('/api/doctor/profile')->json('profile_revision')]))->assertOk()->assertJsonPath('doctor.horaires.lundi.0', '09:00-12:00');
        unset($data['horaires']);
        $this->putJson('/api/doctor/profile', array_merge($data, ['expected_profile_revision' => $this->getJson('/api/doctor/profile')->json('profile_revision')]))->assertOk();
        $this->assertSame(['lundi' => ['09:00-12:00']], $doctor->fresh()->horaires);
    }
}
