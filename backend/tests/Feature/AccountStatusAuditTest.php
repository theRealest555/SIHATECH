<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountStatusAuditTest extends TestCase
{
    use RefreshDatabase;

    public static function routes(): array
    {
        return [['user'], ['admin']];
    }

    private function context(string $kind = 'user'): array
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $actor->id, 'admin_status' => 1]);
        Sanctum::actingAs($actor);
        $target = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        $profile = Admin::factory()->create(['user_id' => $target->id, 'admin_status' => 1]);

        return [$actor, $target, $kind === 'user' ? '/api/admin/users/'.$target->id.'/status' : '/api/admin/admins/'.$profile->id.'/status'];
    }

    #[DataProvider('routes')]
    public function test_access_changes_record_and_project_locked_before_and_after_states(string $kind): void
    {
        [$actor, $target, $url] = $this->context($kind);
        $action = $kind === 'user' ? 'updated_user_status' : 'updated_admin_status';
        $disable = $kind === 'user' ? ['status' => 'inactif'] : ['admin_status' => 0];
        $restore = $kind === 'user' ? ['status' => 'actif'] : ['admin_status' => 1];
        $this->putJson($url, $this->statusDecision($target, $disable))->assertOk();
        $saved = json_decode(AuditLog::where('action', $action)->firstOrFail()->metadata, true);
        $this->assertSame($kind === 'user' ? 'actif' : 1, $saved['from']);
        $this->assertSame($kind === 'user' ? 'inactif' : 0, $saved['to']);
        $this->assertTrue($saved['access_revoked']);
        $this->putJson($url, $this->statusDecision($target, $restore))->assertOk();
        $response = $this->getJson('/api/admin/audit-logs?action='.$action)->assertOk()->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('data.0.details.transition', $kind === 'user' ? 'Account status: Inactive → Active' : 'Administrator approval: Not approved → Approved');
        $response->assertJsonPath('data.1.details.transition', $kind === 'user' ? 'Account status: Active → Inactive' : 'Administrator approval: Approved → Not approved');
        $response->assertJsonPath('data.1.details.access_revoked', true)->assertJsonMissingPath('data.0.details.access_revoked');
        $this->assertSame($actor->id, $response->json('data.0.actor.id'));
    }

    #[DataProvider('routes')]
    public function test_unchanged_active_or_approved_decision_does_not_claim_revocation(string $kind): void
    {
        [$actor, $target, $url] = $this->context($kind);
        $payload = $kind === 'user' ? ['status' => 'actif'] : ['admin_status' => 1];
        $this->putJson($url, $this->statusDecision($target, $payload))->assertOk()->assertJsonPath('changed', false);
        $this->getJson('/api/admin/audit-logs')->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->assertSame(0, $target->fresh()->auth_version);
    }

    public function test_unknown_states_wrong_targets_and_arbitrary_metadata_do_not_become_transitions(): void
    {
        [$actor] = $this->context();
        $cases = [
            ['updated_user_status', 'user', ['from' => ['actif'], 'to' => 'inactif']],
            ['updated_user_status', 'user', ['from' => 'private-status', 'to' => 'inactif']],
            ['updated_user_status', 'admin', ['from' => 'actif', 'to' => 'inactif']],
            ['updated_admin_status', 'admin', ['from' => 'pending', 'to' => 0]],
            ['updated_admin_status', 'admin', ['from' => '1', 'to' => 0]],
            ['updated_admin_status', 'user', ['from' => 1, 'to' => 0]],
            ['legacy_action', 'user', ['from' => 'actif', 'to' => 'inactif']],
        ];
        foreach ($cases as [$action, $type, $metadata]) {
            AuditLog::create(['user_id' => $actor->id, 'action' => $action, 'target_type' => $type, 'target_id' => 123,
                'metadata' => json_encode($metadata + ['access_revoked' => true, 'password' => 'private-password', 'transition' => 'invented transition'])]);
        }
        $response = $this->getJson('/api/admin/audit-logs')->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertSame([], $row['details']);
        }
        $response->assertDontSee('private-status')->assertDontSee('private-password')->assertDontSee('invented transition');
    }

    public function test_legacy_missing_details_are_not_inferred_from_current_account_state(): void
    {
        [$actor, $target] = $this->context();
        AuditLog::create(['user_id' => $actor->id, 'action' => 'updated_user_status', 'target_type' => 'user', 'target_id' => $target->id]);
        AuditLog::create(['user_id' => $actor->id, 'action' => 'updated_admin_status', 'target_type' => 'admin', 'target_id' => $target->admin->id, 'metadata' => 'invalid json']);
        $response = $this->getJson('/api/admin/audit-logs')->assertOk();
        $this->assertSame([], $response->json('data.0.details'));
        $this->assertSame([], $response->json('data.1.details'));
    }
}
