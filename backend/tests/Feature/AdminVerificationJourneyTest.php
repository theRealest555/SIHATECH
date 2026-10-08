<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVerificationJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        Admin::factory()->active()->create(['user_id' => $admin->id]);
        Sanctum::actingAs($admin);
    }

    private function doctor(): Doctor
    {
        $user = User::factory()->create(['role' => 'medecin', 'status' => 'actif', 'email_verified_at' => now()]);

        return Doctor::factory()->create(['user_id' => $user->id, 'is_verified' => false, 'is_active' => true]);
    }

    private function document(Doctor $doctor, array $attributes = []): Document
    {
        $document = Document::factory()->create(array_merge(['doctor_id' => $doctor->id, 'type' => 'licence', 'status' => 'pending'], $attributes));
        Storage::disk('documents')->put($document->file_path, 'private credential');

        return $document;
    }

    public function test_approval_verification_and_required_credential_rejection_are_audited(): void
    {
        $doctor = $this->doctor();
        $document = $this->document($doctor);
        $this->postJson('/api/admin/doctors/'.$doctor->id.'/verify')->assertStatus(400);
        $this->postJson('/api/admin/documents/'.$document->id.'/approve', ['expected_status' => 'pending'])->assertOk();
        $this->postJson('/api/admin/doctors/'.$doctor->id.'/verify')->assertOk()->assertJsonPath('doctor.is_verified', true);
        $this->getJson('/api/public/doctors/'.$doctor->id)->assertOk();
        $this->postJson('/api/admin/documents/'.$document->id.'/reject', ['expected_status' => 'approved', 'rejection_reason' => 'Credential withdrawn.'])->assertOk()->assertJsonPath('doctor_verified', false);
        $this->getJson('/api/public/doctors/'.$doctor->id)->assertNotFound();
        foreach (['approved_document', 'verified_doctor', 'rejected_document', 'revoked_doctor_verification'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_another_approved_required_copy_preserves_verification(): void
    {
        $doctor = $this->doctor();
        $document = $this->document($doctor, ['status' => 'approved']);
        $this->document($doctor, ['status' => 'approved']);
        $doctor->update(['is_verified' => true]);
        $this->postJson('/api/admin/documents/'.$document->id.'/reject', ['rejection_reason' => 'Old copy.'])->assertOk()->assertJsonPath('doctor_verified', true);
    }

    public function test_approved_other_document_cannot_replace_a_medical_licence(): void
    {
        $doctor = $this->doctor();
        $this->document($doctor, ['type' => 'autre', 'status' => 'approved']);
        $this->postJson('/api/admin/doctors/'.$doctor->id.'/verify')->assertStatus(400)->assertJsonPath('missing_document_types.0', 'licence');
    }

    public function test_missing_files_cannot_be_approved_or_used_for_verification(): void
    {
        $doctor = $this->doctor();
        $document = $this->document($doctor);
        Storage::disk('documents')->delete($document->file_path);
        $this->postJson('/api/admin/documents/'.$document->id.'/approve')->assertConflict();
        $document->update(['status' => 'approved']);
        $this->postJson('/api/admin/doctors/'.$doctor->id.'/verify')->assertStatus(400);
        $this->getJson('/api/admin/doctors/'.$doctor->id)->assertJsonPath('data.documents.0.file_available', false)->assertJsonMissingPath('data.documents.0.file_path');
    }

    public function test_stale_review_does_not_overwrite_a_decision(): void
    {
        $doctor = $this->doctor();
        $document = $this->document($doctor);
        $this->postJson('/api/admin/documents/'.$document->id.'/approve', ['expected_status' => 'pending'])->assertOk();
        $this->postJson('/api/admin/documents/'.$document->id.'/reject', ['expected_status' => 'pending', 'rejection_reason' => 'Outdated review.'])->assertConflict();
        $this->assertSame('approved', $document->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_filtered_lists_are_paginated_and_validate_bounds(): void
    {
        $first = $this->doctor();
        $this->doctor();
        $this->getJson('/api/admin/doctors?status=pending&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
        $first->update(['is_verified' => true]);
        $this->getJson('/api/admin/doctors?status=verified')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/admin/doctors?status=all&search='.urlencode($first->user->email))->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/doctors?per_page=101')->assertUnprocessable();
    }

    public function test_repeat_verification_is_idempotent_and_revocation_requires_a_reason(): void
    {
        $doctor = $this->doctor();
        $this->document($doctor, ['status' => 'approved']);
        $this->postJson('/api/admin/doctors/'.$doctor->id.'/verify')->assertOk();
        $this->postJson('/api/admin/doctors/'.$doctor->id.'/verify')->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'verified_doctor')->count());
        $this->postJson('/api/admin/doctors/'.$doctor->id.'/revoke', ['reason' => ' '])->assertUnprocessable();
    }

    public function test_pending_admin_and_patient_cannot_review_private_credentials(): void
    {
        $doctor = $this->doctor();
        $document = $this->document($doctor);
        $pending = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        Admin::factory()->create(['user_id' => $pending->id, 'admin_status' => 0]);
        foreach ([$pending, User::factory()->create(['role' => 'patient', 'status' => 'actif'])] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/doctors')->assertForbidden();
            $this->getJson('/api/admin/doctors/'.$doctor->id)->assertForbidden();
            $this->getJson('/api/admin/documents/'.$document->id.'/download')->assertForbidden();
        }
    }

    public function test_private_paths_are_hidden_in_legacy_views_and_downloads_are_not_cached(): void
    {
        $doctor = $this->doctor();
        $document = $this->document($doctor);
        $this->getJson('/api/admin/doctors/pending')->assertOk()->assertJsonMissingPath('doctors.0.documents.0.file_path');
        $this->getJson('/api/admin/documents/pending')->assertOk()->assertJsonMissingPath('documents.0.file_path');
        $this->getJson('/api/admin/documents/'.$document->id)->assertOk()->assertJsonMissingPath('document.file_path');
        $this->get('/api/admin/documents/'.$document->id.'/download')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
