<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_plans_exclude_inactive_plans_and_expose_only_public_payment_configuration(): void
    {
        config(['services.stripe.key' => 'pk_test_public', 'services.stripe.secret' => 'sk_test_private', 'services.stripe.webhook_secret' => 'whsec_private']);
        $plan = Abonnement::factory()->create(['is_active' => true]);
        Abonnement::factory()->create(['is_active' => false]);
        $response = $this->getJson('/api/subscriptions/plans')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plan->id)->assertJsonPath('data.0.currency', 'MAD')->assertJsonPath('meta.checkout_enabled', true);
        $this->assertStringNotContainsString('sk_test_private', $response->getContent());
        $this->assertStringNotContainsString('whsec_private', $response->getContent());
    }

    public function test_pending_subscription_is_visible_to_its_owner_without_paid_access(): void
    {
        $subscription = UserSubscription::factory()->create(['status' => 'pending', 'payment_method' => ['client_secret' => 'pi_private_secret']]);
        Sanctum::actingAs($subscription->user);
        $this->getJson('/api/subscriptions/current')->assertOk()->assertJsonPath('data.has_access', false)
            ->assertJsonPath('data.subscription.status', 'pending')->assertJsonPath('data.payment_client_secret', 'pi_private_secret')
            ->assertJsonMissingPath('data.subscription.payment_method');
        Sanctum::actingAs(User::factory()->create(['role' => 'patient', 'status' => 'actif']));
        $this->getJson('/api/subscriptions/current')->assertJsonPath('data', null);
    }

    public function test_pending_subscription_blocks_duplicate_creation(): void
    {
        $subscription = UserSubscription::factory()->create(['status' => 'pending']);
        Sanctum::actingAs($subscription->user);
        $this->mock(StripePaymentService::class)->shouldNotReceive('processPayment');
        $plan = Abonnement::factory()->create(['is_active' => true]);
        $this->postJson('/api/subscriptions/subscribe', ['plan_id' => $plan->id, 'expected_plan_version' => 0, 'payment_method_id' => 'pm_test'])->assertConflict();
        $this->assertDatabaseCount('user_subscriptions', 1);
    }

    public function test_provider_failure_does_not_allow_a_second_uncertain_charge(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'patient', 'status' => 'actif']));
        $plan = Abonnement::factory()->create(['is_active' => true]);
        $this->mock(StripePaymentService::class)->shouldReceive('processPayment')->once()->andReturn(['success' => false]);
        $payload = ['plan_id' => $plan->id, 'expected_plan_version' => 0, 'payment_method_id' => 'pm_test'];
        $this->postJson('/api/subscriptions/subscribe', $payload)->assertStatus(400);
        $this->postJson('/api/subscriptions/subscribe', $payload)->assertConflict();
        $this->assertSame('pending', UserSubscription::first()->status);
    }

    public function test_failed_provider_cancellation_preserves_pending_subscription(): void
    {
        $subscription = UserSubscription::factory()->create(['status' => 'pending', 'payment_method' => ['stripe_subscription_id' => 'sub_test']]);
        Sanctum::actingAs($subscription->user);
        $service = $this->mock(StripePaymentService::class);
        $service->shouldReceive('cancelSubscription')->once()->with('sub_test')->andReturn(['success' => false]);
        $this->postJson('/api/subscriptions/cancel')->assertStatus(502);
        $this->assertSame('pending', $subscription->fresh()->status);
    }

    public function test_cancelled_subscription_remains_visible_and_is_not_entitled(): void
    {
        $subscription = UserSubscription::factory()->create(['status' => 'pending', 'payment_method' => ['stripe_subscription_id' => 'sub_test']]);
        Sanctum::actingAs($subscription->user);
        $this->mock(StripePaymentService::class)->shouldReceive('cancelSubscription')->once()->andReturn(['success' => true]);
        $this->postJson('/api/subscriptions/cancel')->assertOk();
        $this->getJson('/api/subscriptions/current')->assertOk()->assertJsonPath('data.subscription.status', 'cancelled')->assertJsonPath('data.has_access', false);
    }

    public function test_six_month_billing_cycle_sets_correct_pending_period(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 31)->startOfDay());
        Sanctum::actingAs(User::factory()->create(['role' => 'patient', 'status' => 'actif']));
        $plan = Abonnement::factory()->create(['is_active' => true, 'billing_cycle' => 'semi-annual']);
        $this->mock(StripePaymentService::class)->shouldReceive('processPayment')->once()->andReturn(['success' => true, 'subscription' => (object) ['id' => 'sub_test'], 'payment' => null, 'client_secret' => 'pi_secret']);
        $this->postJson('/api/subscriptions/subscribe', ['plan_id' => $plan->id, 'expected_plan_version' => 0, 'payment_method_id' => 'pm_test'])->assertOk();
        $this->assertSame('2026-07-31', UserSubscription::first()->ends_at->toDateString());
    }
}
