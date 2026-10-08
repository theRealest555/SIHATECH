<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminStatusAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function statusRoutes(): array
    {
        return [['user'], ['admin']];
    }

    public static function revokedActors(): array
    {
        return [['user', 'suspended'], ['admin', 'suspended'], ['user', 'version'], ['admin', 'version'], ['user', 'demoted'], ['admin', 'demoted']];
    }

    private function context(string $route): array
    {
        // Target before actor also exercises the reverse ID order.
        $target = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        $profile = Admin::factory()->create(['user_id' => $target->id, 'admin_status' => 1]);
        $target->createToken('target-device');
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $actor->id, 'admin_status' => 1]);
        $actor->createToken('actor-device');
        Sanctum::actingAs($actor);
        $url = $route === 'user' ? '/api/admin/users/'.$target->id.'/status' : '/api/admin/admins/'.$profile->id.'/status';
        $payload = $this->statusDecision($target, $route === 'user' ? ['status' => 'inactif'] : ['admin_status' => 0]);

        return [$actor, $target, $profile, $url, $payload];
    }

    #[DataProvider('revokedActors')]
    public function test_revoked_actor_cannot_change_target_access(string $route, string $change): void
    {
        [$actor, $target, $profile, $url, $payload] = $this->context($route);
        if ($change === 'suspended') {
            User::whereKey($actor->id)->update(['status' => 'inactif']);
        } elseif ($change === 'version') {
            User::whereKey($actor->id)->update(['auth_version' => 1]);
        } else {
            Admin::where('user_id', $actor->id)->update(['admin_status' => 0]);
        }
        $this->putJson($url, $payload)->assertForbidden();
        $this->assertSame('actif', $target->fresh()->status);
        $this->assertSame(1, (int) $profile->fresh()->admin_status);
        $this->assertSame(0, $target->fresh()->auth_version);
        $this->assertSame(1, $target->tokens()->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('statusRoutes')]
    public function test_demotion_after_preliminary_authorization_blocks_mutation(string $route): void
    {
        [$actor, $target, $profile, $url, $payload] = $this->context($route);
        $changed = false;
        // Deterministically inject the changed approval before the locked read.
        // This models the boundary; it is not a simultaneous SQLite lock test.
        DB::connection()->beforeExecuting(function ($sql) use ($actor, &$changed) {
            if (! $changed && str_starts_with($sql, 'select') && str_contains($sql, '"users"') && str_contains($sql, ' in (')) {
                $changed = true;
                Admin::where('user_id', $actor->id)->update(['admin_status' => 0]);
            }
        });
        $this->putJson($url, $payload)->assertForbidden();
        $this->assertTrue($changed);
        $this->assertTrue($target->fresh()->isApprovedAdmin());
        $this->assertSame(0, $target->fresh()->auth_version);
        $this->assertSame(1, $target->tokens()->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('statusRoutes')]
    public function test_authorized_change_revokes_only_target_and_records_actor(string $route): void
    {
        [$actor, $target, $profile, $url, $payload] = $this->context($route);
        $this->putJson($url, $payload)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFalse($target->fresh()->isApprovedAdmin());
        $this->assertSame(1, $target->fresh()->auth_version);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertTrue($actor->fresh()->isApprovedAdmin());
        $this->assertSame(0, $actor->fresh()->auth_version);
        $this->assertSame(1, $actor->tokens()->count());
        $log = AuditLog::firstOrFail();
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame($route === 'user' ? $target->id : $profile->id, (int) $log->target_id);
    }

    #[DataProvider('statusRoutes')]
    public function test_audit_failure_rolls_back_access_change(string $route): void
    {
        [$actor, $target, $profile, $url, $payload] = $this->context($route);
        AuditLog::creating(function () {
            throw new \RuntimeException('Fixture audit failure');
        });
        try {
            $this->putJson($url, $payload)->assertStatus(500);
            $this->assertTrue($target->fresh()->isApprovedAdmin());
            $this->assertSame(0, $target->fresh()->auth_version);
            $this->assertSame(1, $target->tokens()->count());
            $this->assertDatabaseCount('audit_logs', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    #[DataProvider('statusRoutes')]
    public function test_missing_target_preserves_not_found_status(string $route): void
    {
        [$actor, $target, $profile, $url, $payload] = $this->context($route);
        $url = $route === 'user' ? '/api/admin/users/999999/status' : '/api/admin/admins/999999/status';
        $this->putJson($url, $payload)->assertNotFound();
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
