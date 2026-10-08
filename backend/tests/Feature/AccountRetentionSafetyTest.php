<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Rendezvous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountRetentionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $user->id, 'admin_status' => 1]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_permanent_deletion_preserves_appointments_files_billing_and_access_state(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create(['role' => 'medecin']);
        $doctor = Doctor::factory()->create(['user_id' => $user->id]);
        $appointment = Rendezvous::factory()->create(['doctor_id' => $doctor->id]);
        $document = Document::factory()->create(['doctor_id' => $doctor->id]);
        $payment = Payment::factory()->create(['user_id' => $user->id, 'user_subscription_id' => null]);
        $user->createToken('existing-device');
        $this->deleteJson('/api/admin/users/'.$user->id)->assertStatus(409)->assertJsonPath('code', 'account_deletion_unavailable');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('rendezvous', ['id' => $appointment->id]);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(0, AuditLog::where('action', 'deleted_user')->count());
    }

    public function test_deactivation_preserves_history_and_revokes_access(): void
    {
        $this->administrator();
        $user = User::factory()->create(['role' => 'patient', 'status' => 'actif']);
        $patient = Patient::factory()->create(['user_id' => $user->id]);
        $appointment = Rendezvous::factory()->create(['patient_id' => $patient->id]);
        $user->createToken('old-device');
        $this->putJson('/api/admin/users/'.$user->id.'/status', $this->statusDecision($user, ['status' => 'inactif']))->assertOk();
        $this->assertDatabaseHas('rendezvous', ['id' => $appointment->id, 'statut' => $appointment->statut]);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $user->fresh()->auth_version);
    }

    public function test_administrators_cannot_disable_their_own_account_or_admin_access(): void
    {
        $admin = $this->administrator();
        $this->putJson('/api/admin/users/'.$admin->id.'/status', $this->statusDecision($admin, ['status' => 'inactif']))->assertStatus(409);
        $this->putJson('/api/admin/admins/'.$admin->admin->id.'/status', $this->statusDecision($admin, ['admin_status' => 0]))->assertStatus(409);
        $this->assertTrue($admin->fresh()->isApprovedAdmin());
        $this->assertSame(0, $admin->fresh()->auth_version);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_listing_uses_stable_pagination_and_validates_filters(): void
    {
        $this->administrator();
        User::factory()->count(11)->create(['role' => 'patient', 'status' => 'actif']);
        $first = $this->getJson('/api/admin/users?role=patient')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('last_page', 2)->assertHeader('Cache-Control', 'no-store, private');
        $second = $this->getJson('/api/admin/users?role=patient&page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame([], array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->assertSame(['id', 'nom', 'prenom', 'email', 'role', 'status', 'created_at', 'status_revision'], array_keys($first->json('data.0')));
        $this->getJson('/api/admin/users?search[]=bad')->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson('/api/admin/users?role=unknown&page=0')->assertUnprocessable()->assertJsonValidationErrors(['role', 'page']);
    }
}
