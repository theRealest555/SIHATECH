<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Document; //
use App\Models\User; //
use Illuminate\Foundation\Testing\RefreshDatabase; //
use Illuminate\Support\Facades\Storage; //
//
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser; //

    protected Doctor $unverifiedDoctor; //

    protected Document $pendingDocument; //

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin', 'status' => 'actif']); //
        Admin::factory()->active()->create(['user_id' => $this->adminUser->id]); //
        Sanctum::actingAs($this->adminUser, ['role:admin']);

        $doctorUser = User::factory()->create(['role' => 'medecin', 'status' => 'actif', 'email_verified_at' => now()]); //
        $this->unverifiedDoctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'is_verified' => false]); //
        Storage::fake('documents');
        $this->pendingDocument = Document::factory()->create(['doctor_id' => $this->unverifiedDoctor->id, 'status' => 'pending']); //
    }

    public function test_admin_can_list_pending_doctors()
    {
        Doctor::factory()->create(['is_verified' => true]); //
        $response = $this->getJson('/api/admin/doctors/pending'); //
        $response->assertStatus(200)
            ->assertJsonCount(1, 'doctors')
            ->assertJsonPath('doctors.0.id', $this->unverifiedDoctor->id);
    }

    public function test_admin_can_list_pending_documents()
    {
        Document::factory()->create(['doctor_id' => $this->unverifiedDoctor->id, 'status' => 'approved', 'type' => 'licence']); //
        $response = $this->getJson('/api/admin/documents/pending'); //
        $response->assertStatus(200)
            ->assertJsonCount(1, 'documents')
            ->assertJsonPath('documents.0.id', $this->pendingDocument->id);
    }

    public function test_admin_can_show_a_specific_document()
    {
        $response = $this->getJson('/api/admin/documents/'.$this->pendingDocument->id); //
        $response->assertStatus(200)
            ->assertJsonPath('document.id', $this->pendingDocument->id);
    }

    public function test_admin_can_approve_a_document()
    {
        Storage::disk('documents')->put($this->pendingDocument->file_path, 'test credential');
        $response = $this->postJson('/api/admin/documents/'.$this->pendingDocument->id.'/approve'); //

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Document approved successfully')
            ->assertJsonPath('document.status', 'approved');
        $this->assertDatabaseHas('documents', ['id' => $this->pendingDocument->id, 'status' => 'approved', 'admin_id' => $this->adminUser->id]); //
        $this->assertDatabaseHas('audit_logs', ['action' => 'approved_document', 'target_id' => $this->pendingDocument->id]); //
    }

    public function test_admin_can_reject_a_document_with_reason()
    {
        $reason = 'Document is not clear.';
        $response = $this->postJson('/api/admin/documents/'.$this->pendingDocument->id.'/reject', [ //
            'rejection_reason' => $reason,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Document rejected successfully')
            ->assertJsonPath('document.status', 'rejected')
            ->assertJsonPath('document.rejection_reason', $reason);
        $this->assertDatabaseHas('documents', ['id' => $this->pendingDocument->id, 'status' => 'rejected', 'rejection_reason' => $reason]); //
        $this->assertDatabaseHas('audit_logs', ['action' => 'rejected_document', 'target_id' => $this->pendingDocument->id]); //
    }

    public function test_admin_can_verify_a_doctor_with_approved_documents()
    {
        Document::factory()->create(['doctor_id' => $this->unverifiedDoctor->id, 'status' => 'approved', 'type' => 'licence']); //

        foreach ($this->unverifiedDoctor->documents as $document) {
            Storage::disk('documents')->put($document->file_path, 'test credential');
        }
        $response = $this->postJson('/api/admin/doctors/'.$this->unverifiedDoctor->id.'/verify'); //

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Doctor verified successfully')
            ->assertJsonPath('doctor.is_verified', true);
        $this->assertDatabaseHas('doctors', ['id' => $this->unverifiedDoctor->id, 'is_verified' => true]); //
        $this->assertDatabaseHas('audit_logs', ['action' => 'verified_doctor', 'target_id' => $this->unverifiedDoctor->id]); //
    }

    public function test_admin_cannot_verify_doctor_without_approved_documents()
    {
        // Ensure no approved documents for $this->unverifiedDoctor
        $this->unverifiedDoctor->documents()->where('status', 'approved')->delete();

        $response = $this->postJson('/api/admin/doctors/'.$this->unverifiedDoctor->id.'/verify'); //

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Approved credential files are required before verification.');
        $this->assertDatabaseHas('doctors', ['id' => $this->unverifiedDoctor->id, 'is_verified' => false]); //
    }

    public function test_admin_can_revoke_doctor_verification_with_reason()
    {
        $verifiedDoctor = Doctor::factory()->create(['is_verified' => true]); //
        $reason = 'Suspicious activity.';

        $response = $this->postJson('/api/admin/doctors/'.$verifiedDoctor->id.'/revoke', [ //
            'reason' => $reason,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Doctor verification revoked successfully')
            ->assertJsonPath('doctor.is_verified', false);
        $this->assertDatabaseHas('doctors', ['id' => $verifiedDoctor->id, 'is_verified' => false]); //
        $this->assertDatabaseHas('audit_logs', [ //
            'action' => 'revoked_doctor_verification',
            'target_id' => $verifiedDoctor->id,
            'metadata' => json_encode(['reason' => $reason]),
        ]);
    }
}
