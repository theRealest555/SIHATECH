<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Document;
use App\Models\Leave;
use App\Models\Patient;
use App\Models\Rendezvous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_cannot_create_an_administrator(): void
    {
        Notification::fake();
        $this->postJson('/api/register', ['nom' => 'Test', 'prenom' => 'Test', 'email' => 'attacker@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_inactive_administrators_cannot_use_login_or_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        Admin::factory()->create(['user_id' => $admin->id, 'admin_status' => 0]);
        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertUnprocessable();
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/doctors/pending')->assertForbidden();
        $this->getJson('/api/admin/reports/financial')->assertForbidden();
    }

    public function test_document_downloads_require_the_owner_or_an_active_admin(): void
    {
        Storage::fake('documents');
        Storage::fake('public');
        $doctor = Doctor::factory()->create();
        $document = Document::factory()->create(['doctor_id' => $doctor->id, 'file_path' => 'doctor-documents/private.pdf']);
        Storage::disk('documents')->put($document->file_path, 'private-content');
        $this->getJson('/api/doctor/documents/'.$document->id.'/download')->assertUnauthorized();
        Sanctum::actingAs($doctor->user);
        $this->get('/api/doctor/documents/'.$document->id.'/download')->assertOk()->assertDownload($document->original_name);
        Sanctum::actingAs(Doctor::factory()->create()->user);
        $this->getJson('/api/doctor/documents/'.$document->id.'/download')->assertNotFound();
        Storage::disk('public')->assertMissing($document->file_path);
    }

    private function bookingContext(): array
    {
        User::factory()->create();
        $user = User::factory()->create(['role' => 'patient', 'status' => 'actif']);
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $doctor = Doctor::factory()->create(['is_verified' => true, 'is_active' => true, 'horaires' => ['lundi' => ['09:00-12:00']]]);
        Sanctum::actingAs($user);

        return [$user, $patient, $doctor, now()->next('monday')->setTime(10, 0)];
    }

    public function test_booking_and_ownership_use_patient_profile_ids(): void
    {
        [$user, $patient, $doctor, $date] = $this->bookingContext();
        $this->assertNotSame($user->id, $patient->id);
        $response = $this->postJson('/api/patient/doctors/'.$doctor->id.'/appointments', ['date_heure' => $date->toDateTimeString()]);
        $response->assertCreated()->assertJsonPath('data.patient_id', $patient->id);
        $this->getJson('/api/patient/appointments')->assertJsonCount(1, 'data');
        $this->patchJson('/api/patient/appointments/'.$response->json('data.id').'/status', ['statut' => 'annulé'])->assertOk();
    }

    public function test_booking_rejects_leave_unverified_doctors_and_off_grid_times(): void
    {
        [$user, $patient, $doctor, $date] = $this->bookingContext();
        $endpoint = '/api/patient/doctors/'.$doctor->id.'/appointments';
        $this->postJson($endpoint, ['date_heure' => $date->copy()->setMinute(15)->toDateTimeString()])->assertConflict();
        $this->postJson($endpoint, ['date_heure' => $date->copy()->setHour(18)->toDateTimeString()])->assertConflict();
        $doctor->update(['is_verified' => false]);
        $this->postJson($endpoint, ['date_heure' => $date->toDateTimeString()])->assertConflict();
        $doctor->update(['is_verified' => true]);
        Leave::factory()->create(['doctor_id' => $doctor->id, 'start_date' => $date->toDateString(), 'end_date' => $date->toDateString()]);
        $this->postJson($endpoint, ['date_heure' => $date->toDateTimeString()])->assertConflict();
        $this->assertSame(0, Rendezvous::count());
    }

    public function test_duplicate_bookings_are_rejected_and_patients_cannot_confirm_visits(): void
    {
        [$user, $patient, $doctor, $date] = $this->bookingContext();
        $endpoint = '/api/patient/doctors/'.$doctor->id.'/appointments';
        $response = $this->postJson($endpoint, ['date_heure' => $date->toDateTimeString()])->assertCreated();
        $this->postJson($endpoint, ['date_heure' => $date->toDateTimeString()])->assertConflict();
        $status = '/api/patient/appointments/'.$response->json('data.id').'/status';
        $this->patchJson($status, ['statut' => 'confirmé'])->assertUnprocessable();
        $this->patchJson($status, ['statut' => 'terminé'])->assertUnprocessable();
        $this->patchJson($status, ['statut' => 'annulé'])->assertOk();
        Sanctum::actingAs($doctor->user);
        $this->patchJson('/api/doctor/appointments/'.$response->json('data.id').'/status', ['statut' => 'confirmé'])->assertConflict();
        $this->assertSame(1, Rendezvous::count());
    }

    public function test_cookie_login_and_logout_do_not_create_or_leave_an_access_token(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'status' => 'actif']);
        $this->withHeaders(['Origin' => 'http://localhost:3000']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonPath('token', null);
        $this->getJson('/api/user')->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }
}
