<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_unknown_and_broker_throttled_addresses_have_identical_responses(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $first = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $token = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk()->assertExactJson($first->json());
        $this->postJson('/api/forgot-password', ['email' => 'unknown@privacy.test'])->assertOk()->assertExactJson($first->json());
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->assertSame($token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@privacy.test']);
    }

    public function test_delivery_failure_has_generic_response_and_sanitized_operator_warning(): void
    {
        User::factory()->create(['email' => 'delivery@privacy.test']);
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('sensitive-provider-detail'));
        Log::shouldReceive('warning')->once()->with('Password reset delivery failed', ['exception' => \RuntimeException::class]);
        $this->postJson('/api/forgot-password', ['email' => 'delivery@privacy.test'])->assertOk()
            ->assertJsonPath('message', 'If an account matches this email, a password reset link will be sent. Check your inbox and spam folder.')
            ->assertDontSee('sensitive-provider-detail');
    }

    public function test_email_limit_is_normalized_and_applies_across_source_ips(): void
    {
        Notification::fake();
        for ($i = 1; $i <= 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])->postJson('/api/forgot-password', ['email' => 'limited@privacy.test'])->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.4'])->postJson('/api/forgot-password', ['email' => 'LIMITED@privacy.test'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->postJson('/api/forgot-password', ['email' => 'limited@privacy.test'])->assertOk();
        Notification::assertNothingSent();
    }

    public function test_ip_limit_blocks_rotating_email_addresses(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/forgot-password', ['email' => 'rotating'.$i.'@privacy.test'])->assertOk();
        }
        $this->postJson('/api/forgot-password', ['email' => 'rotating11@privacy.test'])->assertStatus(429);
    }

    public function test_invalid_email_keeps_validation_without_issuing_tokens(): void
    {
        Notification::fake();
        $this->postJson('/api/forgot-password', ['email' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }
}
