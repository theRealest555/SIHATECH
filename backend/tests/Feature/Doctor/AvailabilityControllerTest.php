<?php

namespace Tests\Feature\Doctor;

use App\Models\Doctor;
use App\Models\Leave;
use App\Models\Rendezvous;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctorUser;

    protected Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doctorUser = User::factory()->create(['role' => 'medecin', 'email_verified_at' => now(), 'status' => 'actif']);
        $this->doctor = Doctor::factory()->create(['user_id' => $this->doctorUser->id, 'is_verified' => true]); // Doctor must be verified for schedule/leave updates
        Sanctum::actingAs($this->doctorUser, ['role:medecin']);
    }

    public function test_doctor_can_get_their_availability()
    {
        // The route /api/public/doctors/{doctor}/availability is public
        // The route /api/doctor/availability is for authenticated doctor
        Sanctum::actingAs($this->doctorUser); // Ensure acting as the doctor
        $response = $this->getJson('/api/doctor/availability'); //

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data' => ['schedule', 'leaves']]);
    }

    public function test_doctor_can_update_their_schedule()
    {
        $newSchedule = [
            'lundi' => ['09:00-12:00', '13:00-17:00'],
            'mardi' => ['10:00-14:00'],
        ];

        $response = $this->putJson('/api/doctor/schedule', ['expected_schedule_revision' => $this->getJson('/api/doctor/availability')->json('data.schedule_revision'), 'schedule' => $newSchedule]); //

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.lundi.0', '09:00-12:00')
            ->assertJsonPath('data.mardi.0', '10:00-14:00');

        $this->assertDatabaseHas('doctors', [
            'id' => $this->doctor->id,
            'horaires' => json_encode($newSchedule),
        ]);
    }

    public function test_doctor_cannot_update_schedule_if_it_conflicts_with_appointments()
    {
        $this->doctor->update(['horaires' => json_encode(['lundi' => ['09:00-17:00']])]);
        Rendezvous::factory()->create([
            'doctor_id' => $this->doctor->id,
            'date_heure' => Carbon::parse('next monday 10:00'),
            'statut' => 'confirmé',
        ]);

        $newConflictingSchedule = [
            'lundi' => ['14:00-18:00'], // This would conflict with 10:00 appointment
        ];

        $response = $this->putJson('/api/doctor/schedule', ['expected_schedule_revision' => $this->getJson('/api/doctor/availability')->json('data.schedule_revision'), 'schedule' => $newConflictingSchedule]); //

        $response->assertStatus(409) // Conflict
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Le nouvel horaire entre en conflit avec des rendez-vous existants.');
    }

    public function test_doctor_can_create_a_leave_period()
    {
        $leaveData = [
            'start_date' => Carbon::today()->addWeek()->toDateString(),
            'end_date' => Carbon::today()->addWeek()->addDays(2)->toDateString(),
            'reason' => 'Vacation',
        ];

        $response = $this->postJson('/api/doctor/leaves', $leaveData); //

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.reason', 'Vacation');

        $this->assertDatabaseHas('leaves', [
            'doctor_id' => $this->doctor->id,
            'reason' => 'Vacation',
        ]);
    }

    public function test_doctor_cannot_create_leave_if_it_conflicts_with_appointments()
    {
        Rendezvous::factory()->create([
            'doctor_id' => $this->doctor->id,
            'date_heure' => Carbon::today()->addDays(3)->setTime(10, 0),
            'statut' => 'confirmé',
        ]);

        $leaveData = [
            'start_date' => Carbon::today()->addDays(3)->toDateString(),
            'end_date' => Carbon::today()->addDays(4)->toDateString(),
            'reason' => 'Conference',
        ];

        $response = $this->postJson('/api/doctor/leaves', $leaveData); //

        $response->assertStatus(409)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'La période de congé entre en conflit avec des rendez-vous existants.');
    }

    public function test_doctor_can_delete_a_leave_period()
    {
        $leave = Leave::factory()->create(['doctor_id' => $this->doctor->id]);

        $response = $this->deleteJson('/api/doctor/leaves/'.$leave->id); //

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Congé supprimé.');
        $this->assertDatabaseMissing('leaves', ['id' => $leave->id]);
    }

    public function test_unverified_doctor_cannot_update_schedule()
    {
        $this->doctor->update(['is_verified' => false]);
        Sanctum::actingAs($this->doctorUser->refresh()); // Refresh user to get updated doctor state

        $response = $this->putJson('/api/doctor/schedule', ['expected_schedule_revision' => $this->getJson('/api/doctor/availability')->json('data.schedule_revision'), 'schedule' => ['lundi' => ['10:00-11:00']]]); //
        $response->assertStatus(403); // Forbidden due to VerifiedDoctor middleware
    }
}
