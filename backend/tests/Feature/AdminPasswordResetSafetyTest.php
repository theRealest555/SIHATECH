<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPasswordResetSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $user->id, 'admin_status' => 1]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(): array
    {
        return ['password' => 'Target-reset-2026!', 'password_confirmation' => 'Target-reset-2026!', 'current_password' => 'password'];
    }

    public function test_missing_or_wrong_actor_password_and_mismatched_confirmation_make_no_changes(): void
    {
        $this->actor();
        $target = User::factory()->create();
        $target->createToken('existing-device');
        $this->putJson('/api/admin/users/'.$target->id.'/password', ['password' => 'Target-reset-2026!'])->assertUnprocessable()->assertJsonValidationErrors(['password', 'current_password']);
        $this->putJson('/api/admin/users/'.$target->id.'/password', array_replace($this->payload(), ['current_password' => 'wrong-password']))->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/api/admin/users/'.$target->id.'/password', array_replace($this->payload(), ['password_confirmation' => 'mismatch']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('password', $target->fresh()->password));
        $this->assertSame(1, $target->tokens()->count());
        $this->assertSame(0, $target->fresh()->auth_version);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_stale_actor_after_password_change_cannot_reset_with_the_new_password(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create();
        app(AccountCredentials::class)->changePassword($actor->id, 'Actor-new-password-2026!');
        Sanctum::actingAs($actor);
        $this->putJson('/api/admin/users/'.$target->id.'/password', array_replace($this->payload(), ['current_password' => 'Actor-new-password-2026!']))->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame(0, $target->fresh()->auth_version);
    }

    public function test_suspension_after_identity_loaded_blocks_reset(): void
    {
        $actor = $this->actor();
        $target = User::factory()->create();
        User::whereKey($actor->id)->update(['status' => 'inactif']);
        $this->putJson('/api/admin/users/'.$target->id.'/password', $this->payload())->assertForbidden();
        $this->assertSame(0, $target->fresh()->auth_version);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_success_changes_only_target_credentials_and_records_no_passwords(): void
    {
        $target = User::factory()->create(['remember_token' => 'old-target-cookie']);
        $target->createToken('target-device');
        // Exercise a target ID lower than the actor ID as well as the normal order.
        $actor = $this->actor();
        $actor->createToken('actor-device');
        $response = $this->putJson('/api/admin/users/'.$target->id.'/password', $this->payload())->assertOk()->assertJsonPath('access_revoked', true)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue(Hash::check('Target-reset-2026!', $target->fresh()->password));
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(1, $target->fresh()->auth_version);
        $this->assertNotSame('old-target-cookie', $target->fresh()->remember_token);
        $this->assertSame(1, $actor->tokens()->count());
        $this->assertSame(0, $actor->fresh()->auth_version);
        $response->assertDontSee('Target-reset-2026!');
        $log = AuditLog::where('action', 'reset_user_password')->firstOrFail();
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame($target->id, (int) $log->target_id);
        $this->assertStringNotContainsString('Target-reset-2026!', $log->toJson());
    }

    public function test_missing_target_is_not_reported_as_server_error(): void
    {
        $this->actor();
        $this->putJson('/api/admin/users/999999/password', $this->payload())->assertNotFound();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reset_rate_limit_blocks_additional_credential_changes(): void
    {
        $this->actor();
        $target = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->putJson('/api/admin/users/'.$target->id.'/password', $this->payload())->assertOk();
        }
        $this->putJson('/api/admin/users/'.$target->id.'/password', $this->payload())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(3, $target->fresh()->auth_version);
        $this->assertDatabaseCount('audit_logs', 3);
    }
}
