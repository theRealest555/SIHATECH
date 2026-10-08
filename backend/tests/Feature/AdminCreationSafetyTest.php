<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCreationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $user->id, 'admin_status' => 1]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(string $email = 'new-admin@creation.test'): array
    {
        return ['nom' => 'Administrator', 'prenom' => 'Fixture', 'email' => $email,
            'password' => 'Creation-fixture-2026!', 'password_confirmation' => 'Creation-fixture-2026!', 'current_password' => 'password'];
    }

    public function test_creation_requires_current_password_and_new_password_confirmation(): void
    {
        $this->actor();
        $payload = $this->payload();
        unset($payload['current_password'], $payload['password_confirmation']);
        $this->postJson('/api/admin/users/admin', $payload)->assertUnprocessable()->assertJsonValidationErrors(['current_password', 'password']);
        $this->postJson('/api/admin/users/admin', array_replace($this->payload(), ['current_password' => 'wrong-password']))->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('admins', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_profile_failure_rolls_back_account_and_audit(): void
    {
        $this->actor();
        Admin::creating(fn () => throw new \RuntimeException('Fixture approval failure'));
        try {
            $this->postJson('/api/admin/users/admin', $this->payload())->assertStatus(500)->assertDontSee('Fixture approval failure');
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('admins', 1);
            $this->assertDatabaseCount('audit_logs', 0);
        } finally {
            Admin::flushEventListeners();
        }
    }

    public function test_audit_failure_rolls_back_account_and_approval_profile(): void
    {
        $this->actor();
        AuditLog::creating(fn () => throw new \RuntimeException('Fixture audit failure'));
        try {
            $this->postJson('/api/admin/users/admin', $this->payload())->assertStatus(500);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('admins', 1);
            $this->assertDatabaseCount('audit_logs', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_stale_actor_cannot_create_after_security_change_or_suspension(): void
    {
        $actor = $this->actor();
        app(AccountCredentials::class)->changePassword($actor->id, 'Actor-new-password-2026!');
        // Simulate credentials loaded before a security change committed.
        Sanctum::actingAs($actor);
        $this->postJson('/api/admin/users/admin', array_replace($this->payload(), ['current_password' => 'Actor-new-password-2026!']))->assertUnprocessable();
        User::whereKey($actor->id)->update(['status' => 'inactif']);
        $this->postJson('/api/admin/users/admin', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_success_is_audited_without_credentials_tokens_or_verification_bypass(): void
    {
        Notification::fake();
        $actor = $this->actor();
        $response = $this->postJson('/api/admin/users/admin', $this->payload() + ['role' => 'patient', 'email_verified_at' => now()->toISOString()])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $new = User::where('email', 'new-admin@creation.test')->firstOrFail();
        $this->assertSame('admin', $new->role);
        $this->assertTrue(Hash::check('Creation-fixture-2026!', $new->password));
        $this->assertFalse($new->hasVerifiedEmail());
        $this->assertSame(0, $new->tokens()->count());
        $response->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.auth_version')->assertDontSee('Creation-fixture-2026!');
        $this->assertDatabaseHas('audit_logs', ['action' => 'created_admin', 'user_id' => $actor->id, 'target_id' => $new->id]);
        Notification::assertNothingSent();
    }

    public function test_creation_throttle_blocks_fourth_request(): void
    {
        $this->actor();
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/admin/users/admin', $this->payload('admin'.$i.'@creation.test'))->assertCreated();
        }
        $this->postJson('/api/admin/users/admin', $this->payload('admin4@creation.test'))->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('audit_logs', 3);
    }
}
