<?php

namespace Tests\Feature;

use App\Jobs\CheckSubscriptionRenewals;
use App\Models\UserSubscription;
use App\Notifications\SubscriptionRenewalNotification;
use App\Services\SubscriptionPeriodAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionPeriodAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    private function subscription(array $attributes = []): UserSubscription
    {
        return UserSubscription::factory()->create(array_merge(['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)], $attributes));
    }

    private function payment(UserSubscription $subscription, string $status = 'completed'): void
    {
        $subscription->payments()->create(['user_id' => $subscription->user_id, 'transaction_id' => 'fixture_'.$subscription->id.'_'.$status,
            'amount' => 199, 'currency' => 'MAD', 'status' => $status, 'payment_method' => 'stripe']);
    }

    public function test_access_scopes_and_api_agree_at_the_exact_period_end(): void
    {
        $subscription = $this->subscription(['ends_at' => now()]);
        $this->assertFalse($subscription->isActive());
        $this->assertTrue($subscription->isExpired());
        $this->assertSame(0, $subscription->getRemainingDays());
        $this->assertSame(0, UserSubscription::active()->count());
        $this->assertSame(1, UserSubscription::expired()->count());
        Sanctum::actingAs($subscription->user);
        $this->getJson('/api/subscriptions/current')->assertOk()->assertJsonPath('data.has_access', false)->assertJsonPath('data.is_expiring_soon', false);
        $subscription->update(['ends_at' => now()->addSecond()]);
        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->isExpired());
        $this->assertSame(1, UserSubscription::active()->count());
    }

    public function test_future_and_pending_periods_do_not_claim_access_or_expiring_access(): void
    {
        foreach ([['starts_at' => now()->addDay()], ['status' => 'pending']] as $attributes) {
            $subscription = $this->subscription($attributes);
            $this->assertFalse($subscription->isActive());
            Sanctum::actingAs($subscription->user);
            $this->getJson('/api/subscriptions/current')->assertOk()->assertJsonPath('data.has_access', false)->assertJsonPath('data.is_expiring_soon', false);
        }
    }

    public function test_audit_counts_anomalies_without_changing_statuses_or_sending_notifications(): void
    {
        Notification::fake();
        $ended = $this->subscription(['ends_at' => now()]);
        $this->payment($ended);
        $invalid = $this->subscription(['starts_at' => now()->addDays(4)]);
        $this->payment($invalid);
        $unpaid = $this->subscription();
        $this->payment($unpaid, 'pending');
        $this->subscription(['status' => 'cancelled']);
        $expected = ['ended_active_periods' => 1, 'invalid_periods' => 1, 'active_without_completed_payment' => 1];
        $this->assertSame($expected, app(SubscriptionPeriodAudit::class)->counts());
        $this->artisan('subscriptions:audit-periods', ['--json' => true])->expectsOutput(json_encode($expected))->assertExitCode(1);
        (new CheckSubscriptionRenewals)->handle();
        (new CheckSubscriptionRenewals)->handle();
        $this->assertSame('active', $ended->fresh()->status);
        $this->assertSame('active', $invalid->fresh()->status);
        $this->assertSame('active', $unpaid->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_clean_audit_succeeds_and_sees_a_later_renewed_period(): void
    {
        $subscription = $this->subscription(['ends_at' => now()->subSecond()]);
        $this->payment($subscription);
        $this->assertSame(1, app(SubscriptionPeriodAudit::class)->counts()['ended_active_periods']);
        $subscription->update(['ends_at' => now()->addMonth()]);
        $this->artisan('subscriptions:audit-periods', ['--json' => true])
            ->expectsOutput('{"ended_active_periods":0,"invalid_periods":0,"active_without_completed_payment":0}')->assertSuccessful();
        (new CheckSubscriptionRenewals)->handle();
        $this->assertTrue($subscription->fresh()->isActive());
    }

    public function test_period_notice_links_to_actual_spa_without_promising_a_charge_or_new_purchase(): void
    {
        config(['app.frontend_url' => 'https://preview.example/']);
        $subscription = $this->subscription();
        $notification = new SubscriptionRenewalNotification($subscription, 3);
        $mail = $notification->toMail($subscription->user);
        $this->assertSame('https://preview.example/my-subscription', $mail->actionUrl);
        $this->assertStringNotContainsString('MAD', implode(' ', $mail->introLines));
        $this->assertArrayNotHasKey('amount', $notification->toArray($subscription->user));
    }
}
