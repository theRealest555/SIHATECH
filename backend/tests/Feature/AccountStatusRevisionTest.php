<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountStatusRevisionTest extends TestCase
{
    use RefreshDatabase;

    public static function routes(): array
    {
        return [['user'], ['admin']];
    }

    private function context(string $kind): array
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $actor->id, 'admin_status' => 1]);
        Sanctum::actingAs($actor);
        $target = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        $admin = Admin::factory()->create(['user_id' => $target->id, 'admin_status' => 1]);
        $url = $kind === 'user' ? '/api/admin/users/'.$target->id.'/status' : '/api/admin/admins/'.$admin->id.'/status';
        $payload = $kind === 'user' ? ['status' => 'inactif'] : ['admin_status' => 0];
        $revision = $this->getJson('/api/admin/users/'.$target->id)->assertOk()->json('status_revision');

        return [$target, $admin, $url, $payload + ['expected_status_revision' => $revision, 'reason' => 'Fixture administrative access review']];
    }

    #[DataProvider('routes')]
    public function test_missing_or_malformed_revision_changes_nothing(string $kind): void
    {
        [$target, $admin, $url, $payload] = $this->context($kind);
        unset($payload['expected_status_revision']);
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('expected_status_revision');
        $this->putJson($url, $payload + ['expected_status_revision' => 'invalid'])->assertUnprocessable();
        $this->assertTrue($target->fresh()->isApprovedAdmin());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('routes')]
    public function test_stale_decision_is_rejected_and_fresh_revision_can_restore_access(string $kind): void
    {
        [$target, $admin, $url, $payload] = $this->context($kind);
        $response = $this->putJson($url, $payload)->assertOk();
        $freshRevision = $response->json($kind === 'user' ? 'user.status_revision' : 'admin.status_revision');
        $restore = $kind === 'user' ? ['status' => 'actif'] : ['admin_status' => 1];
        $this->putJson($url, $restore + ['expected_status_revision' => $payload['expected_status_revision']])->assertConflict();
        $this->assertSame(1, $target->fresh()->auth_version);
        $this->assertFalse($target->fresh()->isApprovedAdmin());
        $this->assertDatabaseCount('audit_logs', 1);
        $this->putJson($url, $restore + ['expected_status_revision' => $freshRevision])->assertOk();
        $this->assertTrue($target->fresh()->isApprovedAdmin());
        $this->assertSame(1, $target->fresh()->auth_version);
    }

    #[DataProvider('routes')]
    public function test_old_revision_stays_invalid_after_access_returns_to_original_status(string $kind): void
    {
        [$target, $admin, $url, $payload] = $this->context($kind);
        $this->putJson($url, $payload)->assertOk();
        $restore = $kind === 'user' ? ['status' => 'actif'] : ['admin_status' => 1];
        $this->putJson($url, $this->statusDecision($target, $restore))->assertOk();
        $target->createToken('new-device');
        $this->putJson($url, $payload)->assertConflict();
        $this->assertTrue($target->fresh()->isApprovedAdmin());
        $this->assertSame(1, $target->tokens()->count());
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_listing_revision_matches_details_without_exposing_authentication_version(): void
    {
        [$target] = $this->context('user');
        $listing = $this->getJson('/api/admin/users?search='.$target->email)->assertOk()->json('data.0');
        $details = $this->getJson('/api/admin/users/'.$target->id)->assertOk()->json('status_revision');
        $this->assertSame($details, $listing['status_revision']);
        $this->assertArrayNotHasKey('auth_version', $listing);
        $this->assertArrayNotHasKey('admin', $listing);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $details);
    }

    public function test_approval_change_also_invalidates_account_status_decision(): void
    {
        [$target, $admin, $url, $payload] = $this->context('user');
        $this->putJson('/api/admin/admins/'.$admin->id.'/status', $this->statusDecision($target, ['admin_status' => 0]))->assertOk();
        $this->putJson($url, $payload)->assertConflict();
        $this->assertSame('actif', $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 1);
    }
}
