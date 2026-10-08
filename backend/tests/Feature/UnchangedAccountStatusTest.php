<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnchangedAccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public static function states(): array
    {
        return [['user', 'actif'], ['user', 'inactif'], ['user', 'en_attente'], ['admin', 0], ['admin', 1]];
    }

    public static function routes(): array
    {
        return [['user'], ['admin']];
    }

    private function context(string $kind, string|int $state): array
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $actor->id, 'admin_status' => 1]);
        Sanctum::actingAs($actor);
        $target = User::factory()->create(['role' => 'admin', 'status' => $kind === 'user' ? $state : 'actif', 'remember_token' => 'existing-cookie']);
        $profile = Admin::factory()->create(['user_id' => $target->id, 'admin_status' => $kind === 'admin' ? $state : 1]);
        $target->createToken('existing-device');
        $recovery = Password::createToken($target);
        $url = $kind === 'user' ? '/api/admin/users/'.$target->id.'/status' : '/api/admin/admins/'.$profile->id.'/status';

        return [$target, $profile, $url, $recovery];
    }

    #[DataProvider('states')]
    public function test_repeated_current_decision_preserves_credentials_revision_timestamps_and_history(string $kind, string|int $state): void
    {
        [$target, $profile, $url, $recovery] = $this->context($kind, $state);
        $payload = $this->statusDecision($target, $kind === 'user' ? ['status' => $state] : ['admin_status' => $state]);
        $userTimestamp = $target->fresh()->updated_at;
        $profileTimestamp = $profile->fresh()->updated_at;
        $this->travel(61)->seconds();
        for ($i = 0; $i < 2; $i++) {
            $this->putJson($url, $payload)->assertOk()->assertJsonPath('changed', false)
                ->assertJsonPath($kind === 'user' ? 'user.status_revision' : 'admin.status_revision', $payload['expected_status_revision'])
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->assertSame(0, $target->fresh()->auth_version);
        $this->assertSame('existing-cookie', $target->fresh()->remember_token);
        $this->assertSame(1, $target->tokens()->count());
        $this->assertTrue(Password::tokenExists($target, $recovery));
        $this->assertTrue($userTimestamp->equalTo($target->fresh()->updated_at));
        $this->assertTrue($profileTimestamp->equalTo($profile->fresh()->updated_at));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('routes')]
    public function test_old_revision_is_rejected_even_when_requested_status_now_matches(string $kind): void
    {
        [$target, $profile, $url] = $this->context($kind, $kind === 'user' ? 'actif' : 1);
        $payload = $this->statusDecision($target, $kind === 'user' ? ['status' => 'inactif'] : ['admin_status' => 0]);
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('changed', true);
        $this->putJson($url, $payload)->assertConflict();
        $this->putJson($url, $this->statusDecision($target, $kind === 'user' ? ['status' => 'inactif'] : ['admin_status' => 0]))
            ->assertOk()->assertJsonPath('changed', false);
        $this->assertSame(1, $target->fresh()->auth_version);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_unknown_legacy_approval_is_not_treated_as_unchanged_zero(): void
    {
        [$target, $profile, $url] = $this->context('admin', 1);
        $profile->update(['admin_status' => 'pending']);
        $this->putJson($url, $this->statusDecision($target, ['admin_status' => 0]))->assertOk()->assertJsonPath('changed', true);
        $this->assertSame(0, (int) $profile->fresh()->admin_status);
        $this->assertSame(1, $target->fresh()->auth_version);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('pending', json_decode(AuditLog::firstOrFail()->metadata, true)['from']);
    }
}
