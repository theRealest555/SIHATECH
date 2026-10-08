<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerificationResendSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_unverified_identity_does_not_resend_after_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);
        User::whereKey($user->id)->update(['email_verified_at' => now()]);
        Sanctum::actingAs($user);
        $this->postJson('/api/email/verification-notification')->assertOk()->assertExactJson(['status' => 'already-verified'])->assertHeader('Cache-Control', 'no-store, private');
        Notification::assertNothingSent();
    }

    public function test_resend_uses_current_email_instead_of_stale_authenticated_identity(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);
        User::whereKey($user->id)->update(['email' => 'current@verification.test']);
        Sanctum::actingAs($user);
        $this->postJson('/api/email/verification-notification')->assertOk()->assertJsonPath('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmailNotification::class, fn ($notification, $channels, $recipient) => $recipient->email === 'current@verification.test');
    }

    public function test_stale_active_identity_cannot_send_after_suspension(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null, 'status' => 'actif']);
        User::whereKey($user->id)->update(['status' => 'inactif']);
        Sanctum::actingAs($user);
        $this->postJson('/api/email/verification-notification')->assertForbidden();
        Notification::assertNothingSent();
    }

    public function test_delivery_failure_has_retryable_status_and_sanitized_warning(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('private transport details'));
        Log::shouldReceive('warning')->once()->with('Verification email delivery failed', ['exception' => \RuntimeException::class]);
        Sanctum::actingAs($user);
        $this->postJson('/api/email/verification-notification')->assertStatus(503)->assertDontSee('private transport details')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_throttle_prevents_seventh_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);
        Sanctum::actingAs($user);
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/email/verification-notification')->assertOk();
        }
        $this->postJson('/api/email/verification-notification')->assertStatus(429)->assertHeader('Retry-After');
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 6);
    }

    public function test_notification_uses_configured_verification_expiration(): void
    {
        config(['verification.expire' => 10]);
        $user = User::factory()->create(['email_verified_at' => null]);
        $mail = (new VerifyEmailNotification)->toMail($user);
        parse_str(parse_url($mail->viewData['verificationUrl'], PHP_URL_QUERY), $query);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, (int) $query['expires'], 1);
        $this->assertStringContainsString('/api/email/verify/'.$user->id.'/'.sha1($user->email), $mail->viewData['verificationUrl']);
    }
}
