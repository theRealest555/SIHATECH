<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    public function test_attendance_audit_exposes_only_the_known_transition_and_appointment_target(): void
    {
        AuditLog::create(['user_id' => $this->admin->id, 'action' => 'marked_appointment_no_show', 'target_type' => 'App\\Models\\Rendezvous', 'target_id' => 42,
            'metadata' => json_encode(['from' => 'confirmé', 'to' => 'no_show', 'secret' => 'private fixture'])]);
        $this->getJson('/api/admin/audit-logs?action=marked_appointment_no_show')->assertOk()
            ->assertJsonPath('data.0.target.type', 'Appointment')->assertJsonPath('data.0.details.transition', 'Confirmed → No-show')->assertDontSee('private fixture');
    }

    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->active()->create(['user_id' => $this->admin->id]);
        Sanctum::actingAs($this->admin);
    }

    private function log(User $actor, string $action, string $date, array $metadata = []): AuditLog
    {
        $log = AuditLog::create(['user_id' => $actor->id, 'action' => $action, 'target_type' => 'App\\Models\\Document', 'target_id' => 123, 'metadata' => json_encode($metadata)]);
        $log->forceFill(['created_at' => $date])->save();

        return $log;
    }

    public function test_list_is_stably_paginated_and_exposes_only_safe_fields(): void
    {
        $first = $this->log($this->admin, 'approved_document', '2026-10-07 10:00:00');
        $last = $this->log($this->admin, 'rejected_document', '2026-10-07 10:00:00', ['reason' => 'Expired credential', 'document_id' => 123, 'password' => 'secret', 'token' => 'private', 'email' => 'private@example.test']);
        $response = $this->getJson('/api/admin/audit-logs?per_page=1');
        $response->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $last->id)->assertJsonPath('data.0.actor.id', $this->admin->id)
            ->assertJsonPath('data.0.target.type', 'Document')->assertJsonPath('data.0.details.reason', 'Expired credential')
            ->assertJsonMissingPath('data.0.details.password')->assertJsonMissingPath('data.0.actor.email')->assertJsonMissingPath('data.0.metadata');
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
        $this->getJson('/api/admin/audit-logs?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $first->id);
    }

    public function test_actor_action_and_inclusive_date_filters_are_applied_together(): void
    {
        $other = User::factory()->create();
        $this->log($this->admin, 'approved_document', '2026-10-07 00:00:00');
        $last = $this->log($this->admin, 'approved_document', '2026-10-07 23:59:59');
        $this->log($this->admin, 'approved_document', '2026-10-08 00:00:00');
        $this->log($this->admin, 'rejected_document', '2026-10-07 12:00:00');
        $this->log($other, 'approved_document', '2026-10-07 12:00:00');
        $this->getJson('/api/admin/audit-logs?user_id='.$this->admin->id.'&action=approved_document&start_date=2026-10-07&end_date=2026-10-07')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $last->id);
        $this->getJson('/api/admin/audit-logs?end_date=2026-10-06')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_invalid_filters_and_non_admin_access_are_rejected(): void
    {
        foreach (['per_page=101', 'page=0', 'user_id=-1', 'action=%25', 'start_date=2026-10-08&end_date=2026-10-07', 'start_date=2026-02-30'] as $filter) {
            $this->getJson('/api/admin/audit-logs?'.$filter)->assertUnprocessable();
        }
        foreach (['patient', 'medecin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'status' => 'actif', 'email_verified_at' => now()]));
            $this->getJson('/api/admin/audit-logs')->assertForbidden();
        }
        $pending = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $pending->id, 'admin_status' => 'pending']);
        Sanctum::actingAs($pending);
        $this->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    public function test_deleting_an_actor_retains_history_without_claiming_a_system_action(): void
    {
        $former = User::factory()->create();
        $log = $this->log($former, 'approved_document', '2026-10-07 10:00:00');
        $former->delete();
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'user_id' => null, 'action' => 'approved_document']);
        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.actor', null);
    }

    public function test_user_deactivation_rolls_back_when_its_audit_entry_cannot_be_saved(): void
    {
        $target = User::factory()->create(['role' => 'patient', 'status' => 'actif']);
        $target->createToken('existing-device');
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->putJson('/api/admin/users/'.$target->id.'/status', $this->statusDecision($target, ['status' => 'inactif']))->assertStatus(500);
            $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'actif', 'auth_version' => 0]);
            $this->assertSame(1, $target->tokens()->count());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_legacy_unknown_targets_and_metadata_do_not_break_history(): void
    {
        $log = $this->log($this->admin, 'legacy_action', '2026-10-07 10:00:00');
        $log->forceFill(['target_type' => 'Legacy\\Unknown', 'metadata' => 'null'])->save();
        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonPath('data.0.target.type', 'Other record')->assertJsonPath('data.0.details', []);
    }
}
