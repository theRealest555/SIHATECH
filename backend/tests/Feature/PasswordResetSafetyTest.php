<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $email, string $token): array
    {
        return ['email' => $email, 'token' => $token, 'password' => 'Reset-safety-2026!', 'password_confirmation' => 'Reset-safety-2026!'];
    }

    public function test_consumed_token_cannot_reset_again_or_revoke_new_credentials(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create();
        $user->createToken('old-device');
        $token = Password::createToken($user);
        $this->postJson('/api/reset-password', $this->payload($user->email, $token))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $user->refresh();
        $this->assertSame(1, $user->auth_version);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check('Reset-safety-2026!', $user->password));
        $user->createToken('new-device');
        $this->postJson('/api/reset-password', $this->payload($user->email, $token))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->assertSame(1, $user->tokens()->count());
        Event::assertDispatchedTimes(PasswordReset::class, 1);
    }

    public function test_token_delete_failure_rolls_back_password_revocation_and_preserves_token(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create();
        $originalPassword = $user->password;
        $user->createToken('existing-device');
        $token = Password::createToken($user);
        DB::listen(function ($query) {
            if (str_starts_with(strtolower($query->sql), 'delete') && str_contains($query->sql, 'password_reset_tokens')) {
                throw new \RuntimeException('Fixture token deletion failure');
            }
        });
        $this->postJson('/api/reset-password', $this->payload($user->email, $token))->assertStatus(500);
        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
        $this->assertSame(0, $user->auth_version);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertTrue(Password::tokenExists($user, $token));
        Event::assertNotDispatched(PasswordReset::class);
    }

    public function test_expired_and_unknown_account_resets_have_same_error_and_no_changes(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $expired = $this->postJson('/api/reset-password', $this->payload($user->email, $token))->assertUnprocessable();
        $this->postJson('/api/reset-password', $this->payload('unknown@reset.test', $token))->assertUnprocessable()->assertExactJson($expired->json());
        $this->assertSame(0, $user->fresh()->auth_version);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_attempts_are_limited_per_normalized_address_across_ips(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$i])->postJson('/api/reset-password', $this->payload('limited@reset.test', 'invalid'))->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.6'])->postJson('/api/reset-password', $this->payload('LIMITED@reset.test', 'invalid'))->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->postJson('/api/reset-password', $this->payload('limited@reset.test', 'invalid'))->assertUnprocessable();
    }

    public function test_ip_limit_applies_across_reset_addresses(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/reset-password', $this->payload('ip'.$i.'@reset.test', 'invalid'))->assertUnprocessable();
        }
        $this->postJson('/api/reset-password', $this->payload('ip11@reset.test', 'invalid'))->assertStatus(429);
    }

    public function test_non_string_and_oversized_reset_tokens_are_validation_errors(): void
    {
        foreach ([['token-array'], str_repeat('x', 513)] as $token) {
            $payload = $this->payload('input@reset.test', 'invalid');
            $payload['token'] = $token;
            $this->postJson('/api/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('token');
        }
    }
}
