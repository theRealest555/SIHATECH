<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Leave;
use App\Models\Patient;
use App\Models\Rendezvous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->travelTo(now()->next('monday')->setTime(8, 0));
        $doctor = Doctor::factory()->create(['is_verified' => true, 'is_active' => true, 'horaires' => ['lundi' => ['09:00-10:00']]]);
        User::factory()->create();
        $patient = Patient::factory()->create();

        return [$doctor, $patient, today()->toDateString()];
    }

    public function test_search_booking_confirmation_and_cancellation_use_one_slot_contract(): void
    {
        [$doctor, $patient, $date] = $this->context();
        $search = '/api/public/doctors/search?name='.urlencode($doctor->full_name).'&date='.$date;
        $this->getJson($search)->assertOk()->assertJsonPath('data.0.available_slots', ['09:00', '09:30']);
        $this->getJson('/api/public/doctors/'.$doctor->id)->assertJsonPath('meta.timezone', 'UTC');
        Sanctum::actingAs($patient->user);
        $booking = $this->postJson('/api/patient/doctors/'.$doctor->id.'/appointments', ['date_heure' => $date.' 09:00:00'])
            ->assertCreated()->assertJsonPath('data.patient_id', $patient->id);
        $id = $booking->json('data.id');
        $this->getJson('/api/patient/appointments?period=upcoming')->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.statut', 'en_attente')->assertJsonPath('data.0.starts_at', $date.'T09:00:00+00:00');
        $this->getJson($search)->assertJsonPath('data.0.available_slots', ['09:30']);
        $this->getJson('/api/public/doctors/'.$doctor->id.'/slots?date='.$date)->assertJsonPath('data', ['09:30']);
        Sanctum::actingAs($doctor->user);
        $this->getJson('/api/doctor/appointments?period=upcoming')->assertJsonPath('data.0.patient_name', $patient->user->prenom.' '.$patient->user->nom);
        $this->patchJson('/api/doctor/appointments/'.$id.'/status', ['statut' => 'confirmé'])->assertOk();
        Sanctum::actingAs($patient->user);
        $this->patchJson('/api/patient/appointments/'.$id.'/status', ['statut' => 'annulé'])->assertOk();
        $this->getJson('/api/patient/appointments?period=cancelled')->assertJsonPath('data.0.statut', 'annulé');
        $this->getJson('/api/patient/appointments?period=upcoming')->assertJsonCount(0, 'data');
        $this->getJson($search)->assertJsonPath('data.0.available_slots', ['09:00', '09:30']);
    }

    public function test_search_slots_respect_leave_and_partial_overlap(): void
    {
        [$doctor, $patient, $date] = $this->context();
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'date_heure' => $date.' 09:15:00', 'statut' => 'confirmé']);
        $this->getJson('/api/public/doctors/search?date='.$date)->assertJsonPath('data.0.available_slots', []);
        Leave::factory()->create(['doctor_id' => $doctor->id, 'start_date' => $date, 'end_date' => $date]);
        $this->getJson('/api/public/doctors/'.$doctor->id.'/slots?date='.$date)->assertJsonPath('meta.is_on_leave', true)->assertJsonPath('data', []);
    }

    public function test_disabled_doctors_are_not_discoverable_or_bookable(): void
    {
        [$doctor, $patient, $date] = $this->context();
        $doctor->user->update(['status' => 'inactif']);
        $this->getJson('/api/public/doctors/search')->assertJsonCount(0, 'data');
        $this->getJson('/api/public/doctors')->assertJsonCount(0, 'data');
        foreach (['', '/availability', '/slots?date='.$date, '/statistics'] as $suffix) {
            $this->getJson('/api/public/doctors/'.$doctor->id.$suffix)->assertNotFound();
        }
        Sanctum::actingAs($patient->user);
        $this->postJson('/api/patient/doctors/'.$doctor->id.'/appointments', ['date_heure' => $date.' 09:00:00'])->assertConflict();
    }

    public function test_appointment_pagination_preserves_ownership_and_periods(): void
    {
        [$doctor, $patient] = $this->context();
        foreach (range(1, 4) as $days) {
            Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'date_heure' => now()->addDays($days), 'statut' => 'en_attente']);
        }
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'date_heure' => now()->subDay(), 'statut' => 'no_show']);
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'patient_id' => $patient->id, 'date_heure' => now()->addDay(), 'statut' => 'annulé']);
        Rendezvous::factory()->create(['doctor_id' => $doctor->id]);
        Sanctum::actingAs($patient->user);
        $first = $this->getJson('/api/patient/appointments?period=upcoming&per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2);
        $second = $this->getJson('/api/patient/appointments?period=upcoming&per_page=2&page=2')->assertJsonCount(2, 'data');
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->getJson('/api/patient/appointments?period=past')->assertJsonCount(1, 'data')->assertJsonPath('data.0.statut', 'no_show');
        $this->getJson('/api/patient/appointments?period=cancelled')->assertJsonCount(1, 'data');
        $this->getJson('/api/patient/appointments?doctor_id=999&patient_id=999&period=all')->assertJsonCount(6, 'data');
    }

    public function test_invalid_filters_return_validation_errors(): void
    {
        [$doctor, $patient] = $this->context();
        Sanctum::actingAs($patient->user);
        $this->getJson('/api/patient/appointments?date=not-a-date&per_page=10000&period=invalid')->assertUnprocessable()->assertJsonValidationErrors(['date', 'per_page', 'period']);
        $this->getJson('/api/public/doctors/search?date=not-a-date&page=0')->assertUnprocessable()->assertJsonValidationErrors(['date', 'page']);
    }

    public function test_unapproved_admin_cannot_use_general_appointment_listing(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Admin::factory()->create(['user_id' => $user->id, 'admin_status' => 0]);
        Sanctum::actingAs($user);
        $this->getJson('/api/appointments')->assertForbidden();
    }

    public function test_booking_times_include_the_configured_timezone_offset(): void
    {
        $originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'Africa/Casablanca']);
        date_default_timezone_set('Africa/Casablanca');
        try {
            $this->travelTo(Carbon::parse('2026-10-06 08:00:00', 'Africa/Casablanca'));
            $doctor = Doctor::factory()->create(['is_verified' => true, 'is_active' => true, 'horaires' => ['mercredi' => ['09:00-10:00']]]);
            $patient = Patient::factory()->create();
            Sanctum::actingAs($patient->user);
            $this->getJson('/api/public/doctors/'.$doctor->id.'/slots?date=2026-10-07')->assertJsonPath('meta.timezone', 'Africa/Casablanca');
            $this->postJson('/api/patient/doctors/'.$doctor->id.'/appointments', ['date_heure' => '2026-10-07 09:00:00'])->assertCreated();
            $this->getJson('/api/patient/appointments?period=upcoming')->assertJsonPath('meta.timezone', 'Africa/Casablanca')
                ->assertJsonPath('data.0.starts_at', '2026-10-07T09:00:00+01:00');
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }
}
