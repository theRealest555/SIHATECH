<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountAccessReasonTest extends TestCase
{
    use RefreshDatabase;

    public static function removals(): array
    {
        return [['inactif'], ['en_attente'], ['demote']];
    }

    public static function restorations(): array
    {
        return [['actif'], ['approve']];
    }

    private function context(string $decision): array
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $actor->id, 'admin_status' => 1]);
        Sanctum::actingAs($actor);
        $target = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        $admin = Admin::factory()->create(['user_id' => $target->id, 'admin_status' => 1]);
        $target->createToken('existing-device');
        $approval = in_array($decision, ['demote', 'approve'], true);
        $url = $approval ? '/api/admin/admins/'.$admin->id.'/status' : '/api/admin/users/'.$target->id.'/status';
        $payload = $approval ? ['admin_status' => $decision === 'demote' ? 0 : 1] : ['status' => $decision];

        return [$target, $url, $this->statusDecision($target, $payload)];
    }

    #[DataProvider('removals')]
    public function test_invalid_or_missing_removal_reason_preserves_access_and_history(string $decision): void
    {
        [$target, $url, $payload] = $this->context($decision);
        unset($payload['reason']);
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('reason');
        foreach ([null, '', '   ', "\u{00A0}\u{2003}", ['unexpected'], str_repeat('x', 501)] as $reason) {
            $this->putJson($url, $payload + ['reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        $this->assertTrue($target->fresh()->isApprovedAdmin());
        $this->assertSame(0, $target->fresh()->auth_version);
        $this->assertSame(1, $target->tokens()->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('removals')]
    public function test_trimmed_removal_reason_is_recorded_and_projected_with_transition(string $decision): void
    {
        [$target, $url, $payload] = $this->context($decision);
        $payload['reason'] = "\u{2003}Administrative access review\u{00A0}";
        $this->putJson($url, $payload)->assertOk()->assertDontSee('Administrative access review');
        $saved = json_decode(AuditLog::firstOrFail()->metadata, true);
        $this->assertSame('Administrative access review', $saved['reason']);
        $this->assertTrue($saved['access_revoked']);
        $this->getJson('/api/admin/audit-logs')->assertOk()
            ->assertJsonPath('data.0.details.reason', 'Administrative access review')
            ->assertJsonPath('data.0.details.access_revoked', true);
        $this->assertSame(0, $target->tokens()->count());
    }

    #[DataProvider('restorations')]
    public function test_reason_is_optional_when_access_is_not_removed(string $decision): void
    {
        [$target, $url, $payload] = $this->context($decision);
        unset($payload['reason']);
        $this->putJson($url, $payload)->assertOk();
        $this->assertSame(1, $target->tokens()->count());
        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonMissingPath('data.0.details.reason');
    }

    public function test_maximum_length_unicode_reason_is_preserved_without_truncation(): void
    {
        [$target, $url, $payload] = $this->context('inactif');
        $payload['reason'] = str_repeat('é', 500);
        $this->putJson($url, $payload)->assertOk();
        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonPath('data.0.details.reason', $payload['reason']);
    }
}
