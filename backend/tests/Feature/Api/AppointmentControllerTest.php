<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rendezvous;
use App\Models\Speciality;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $patientUser;

    protected Patient $patient;

    protected User $doctorUser;

    protected Doctor $doctor;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Patient
        $this->patientUser = User::factory()->create(['role' => 'patient', 'email_verified_at' => now(), 'status' => 'actif']); //
        $this->patient = Patient::factory()->create(['user_id' => $this->patientUser->id]); //

        // Doctor
        $this->doctorUser = User::factory()->create(['role' => 'medecin', 'email_verified_at' => now(), 'status' => 'actif']); //
        $speciality = Speciality::factory()->create(); //
        $this->doctor = Doctor::factory()->create([ //
            'user_id' => $this->doctorUser->id,
            'speciality_id' => $speciality->id,
            'is_verified' => true,
            'horaires' => ['lundi' => ['09:00-12:00', '14:00-17:00'], 'mardi' => ['09:00-12:00']],
        ]);

        // Admin
        $this->adminUser = User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'status' => 'actif']); //
        Admin::factory()->active()->create(['user_id' => $this->adminUser->id]); //
    }

    public function test_get_available_slots_for_a_doctor_on_a_specific_date()
    {
        Sanctum::actingAs($this->patientUser, ['role:patient']);
        $testDate = Carbon::parse('next monday')->format('Y-m-d');

        Rendezvous::factory()->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'date_heure' => Carbon::parse($testDate.' 09:00:00'),
            'statut' => 'confirmé',
        ]);

        $response = $this->getJson("/api/public/doctors/{$this->doctor->id}/slots?date={$testDate}"); //
        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data', 'meta']) //
            ->assertJsonFragment(['09:30']) //
            ->assertJsonMissing(['09:00']); //
    }

    public function test_patient_can_book_an_appointment()
    {
        Sanctum::actingAs($this->patientUser, ['role:patient']);
        $slotToBook = Carbon::parse('next monday 10:00:00');

        $response = $this->postJson("/api/patient/doctors/{$this->doctor->id}/appointments", [ //
            'date_heure' => $slotToBook->toDateTimeString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success') //
            ->assertJsonPath('data.doctor_id', $this->doctor->id) //
            ->assertJsonPath('data.patient_id', $this->patient->id) //
            ->assertJsonPath('data.statut', 'en_attente'); //

        $this->assertDatabaseHas('rendezvous', [
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'date_heure' => $slotToBook->toDateTimeString(),
        ]);
    }

    public function test_patient_cannot_book_taken_slot()
    {
        Sanctum::actingAs($this->patientUser, ['role:patient']);
        $slot = Carbon::parse('next monday 10:00:00');
        $otherPatient = Patient::factory()->create(); //
        Rendezvous::factory()->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $otherPatient->id,
            'date_heure' => $slot,
            'statut' => 'confirmé',
        ]);

        $response = $this->postJson("/api/patient/doctors/{$this->doctor->id}/appointments", [ //
            'date_heure' => $slot->toDateTimeString(),
        ]);
        $response->assertStatus(409)
            ->assertJsonPath('message', 'This time slot is no longer available'); //
    }

    public function test_patient_can_cancel_their_appointment()
    {
        Sanctum::actingAs($this->patientUser, ['role:patient']);
        $appointment = Rendezvous::factory()->create([ //
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'statut' => 'confirmé',
            'date_heure' => now()->addDay(),
        ]);

        $response = $this->patchJson("/api/patient/appointments/{$appointment->id}/status", ['statut' => 'annulé']); //

        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'annulé'); //
        $this->assertDatabaseHas('rendezvous', ['id' => $appointment->id, 'statut' => 'annulé']);
    }

    public function test_doctor_can_update_appointment_status()
    {
        Sanctum::actingAs($this->doctorUser, ['role:medecin']);
        $appointment = Rendezvous::factory()->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'statut' => 'en_attente',
        ]);

        $response = $this->patchJson("/api/doctor/appointments/{$appointment->id}/status", ['statut' => 'confirmé']); //

        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'confirmé'); //
        $this->assertDatabaseHas('rendezvous', ['id' => $appointment->id, 'statut' => 'confirmé']);
    }

    public function test_doctor_can_mark_appointment_as_no_show()
    {
        Sanctum::actingAs($this->doctorUser, ['role:medecin']);
        $appointment = Rendezvous::factory()->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'date_heure' => now()->subHour(),
            'statut' => 'confirmé',
        ]);

        $response = $this->postJson("/api/doctor/appointments/{$appointment->id}/no-show", ['reason' => 'Patient did not arrive']); //
        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'no_show'); //
        $this->assertDatabaseHas('rendezvous', ['id' => $appointment->id, 'statut' => 'no_show']);
    }

    public function test_admin_can_mark_appointment_as_no_show()
    {
        Sanctum::actingAs($this->adminUser, ['role:admin']);
        $appointment = Rendezvous::factory()->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'date_heure' => now()->subHour(),
            'statut' => 'confirmé',
        ]);
        $response = $this->postJson("/api/admin/appointments/{$appointment->id}/no-show", ['reason' => 'Admin marked no-show']); //
        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'no_show'); //
    }

    public function test_patient_can_get_their_appointments()
    {
        Sanctum::actingAs($this->patientUser, ['role:patient']);
        Rendezvous::factory(3)->create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id]); //
        Rendezvous::factory(2)->create(); //

        $response = $this->getJson('/api/appointments'); //
        $response->assertStatus(200)
            ->assertJsonPath('data.0.patient_id', $this->patientUser->id) //
            ->assertJsonCount(3, 'data'); //
        foreach ($response->json('data') as $appt) {
            $this->assertEquals($this->patientUser->id, $appt['patient_id']);
        }
    }

    public function test_doctor_can_get_their_appointments()
    {
        Sanctum::actingAs($this->doctorUser, ['role:medecin']);
        Rendezvous::factory(4)->create(['doctor_id' => $this->doctor->id, 'patient_id' => $this->patient->id]); //
        Rendezvous::factory(2)->create(); //

        $response = $this->getJson('/api/appointments'); //
        $response->assertStatus(200)
            ->assertJsonCount(4, 'data'); //
        foreach ($response->json('data') as $appt) {
            $this->assertEquals($this->doctor->id, $appt['doctor_id']);
        }
    }

    public function test_admin_can_get_all_appointments_or_filter()
    {
        Sanctum::actingAs($this->adminUser, ['role:admin']);
        Rendezvous::factory(5)->create(['patient_id' => $this->patient->id]); //
        $doctorForFilter = Doctor::factory()->create(); //
        Rendezvous::factory(2)->create(['doctor_id' => $doctorForFilter->id, 'patient_id' => $this->patient->id]); //

        $responseAll = $this->getJson('/api/appointments'); //
        $responseAll->assertStatus(200)
            ->assertJsonCount(Rendezvous::count(), 'data'); //

        $responseFiltered = $this->getJson('/api/appointments?doctor_id='.$doctorForFilter->id); //
        $responseFiltered->assertStatus(200)
            ->assertJsonCount(2, 'data'); //
    }

    public function test_doctor_can_get_no_show_statistics()
    {
        Sanctum::actingAs($this->doctorUser, ['role:medecin']);

        Rendezvous::factory()->count(5)->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'statut' => 'terminé',
            'date_heure' => now()->subDays(5),
        ]);
        Rendezvous::factory()->count(3)->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'statut' => 'no_show',
            'date_heure' => now()->subDays(3),
        ]);
        Rendezvous::factory()->count(2)->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'statut' => 'annulé',
            'date_heure' => now()->subDays(2),
        ]);
        $repeatPatientUser = User::factory()->create(['role' => 'patient']); // Create user for patient
        $repeatPatient = Patient::factory()->create(['user_id' => $repeatPatientUser->id]); // Create patient profile
        Rendezvous::factory()->count(2)->create([ //
            'doctor_id' => $this->doctor->id,
            'patient_id' => $repeatPatient->id,
            'statut' => 'no_show',
            'date_heure' => now()->subDays(1),
        ]);

        $startDate = now()->subMonth()->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson("/api/doctor/appointments/no-show-stats?start_date={$startDate}&end_date={$endDate}"); //

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success') //
            ->assertJsonStructure([ //
                'data' => [
                    'summary' => [
                        'total_appointments',
                        'no_shows',
                        'completed',
                        'cancelled',
                        'no_show_rate',
                    ],
                    'repeat_no_shows' => [
                        '*' => ['patient_id', 'patient_name', 'no_show_count'],
                    ],
                    'period',
                ],
            ])
            ->assertJsonPath('data.summary.total_appointments', 12) //
            ->assertJsonPath('data.summary.no_shows', 5) //
            ->assertJsonPath('data.summary.completed', 5) //
            ->assertJsonPath('data.summary.cancelled', 2); //

        // The patient_id in repeat_no_shows refers to the ID from the `patients` table.
        $response->assertJsonFragment(['patient_id' => $repeatPatient->id, 'no_show_count' => 2]); //
    }

    public function test_non_doctor_cannot_get_no_show_stats()
    {
        Sanctum::actingAs($this->patientUser, ['role:patient']);
        $response = $this->getJson('/api/doctor/appointments/no-show-stats'); //
        $response->assertStatus(403);
    }
}
