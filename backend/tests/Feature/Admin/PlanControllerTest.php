<?php

namespace Tests\Feature\Admin;

use App\Models\Abonnement;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->active()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);
    }

    private function draft(array $changes = []): array
    {
        return array_replace(['name' => 'Professional', 'description' => 'Booking tools', 'price' => '199.00', 'billing_cycle' => 'monthly', 'features' => ['Appointment booking'], 'is_active' => false, 'stripe_price_id' => null], $changes);
    }

    public function test_drafts_can_be_created_edited_and_listed_with_audit_and_versions(): void
    {
        $this->mock(StripePaymentService::class)->shouldNotReceive('assertPlanPrice');
        $id = $this->postJson('/api/admin/subscription-plans', $this->draft())->assertCreated()->assertJsonPath('data.version', 1)->json('data.id');
        $this->putJson('/api/admin/subscription-plans/'.$id, $this->draft(['price' => '249.00', 'expected_version' => 1]))->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson('/api/admin/subscription-plans?per_page=1')->assertOk()->assertJsonPath('data.0.price', '249.00')->assertJsonPath('data.0.features', ['Appointment booking'])->assertJsonPath('data.0.subscriptions_count', 0);
        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonPath('data.0.target.type', 'Subscription plan');
        $this->getJson('/api/subscriptions/plans')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_stale_edits_and_identical_retries_do_not_overwrite_or_duplicate_audit(): void
    {
        $id = $this->postJson('/api/admin/subscription-plans', $this->draft())->assertCreated()->json('data.id');
        $this->putJson('/api/admin/subscription-plans/'.$id, $this->draft(['expected_version' => 1]))->assertOk()->assertJsonPath('data.version', 1);
        $this->putJson('/api/admin/subscription-plans/'.$id, $this->draft(['name' => 'Changed', 'expected_version' => 1]))->assertOk();
        $this->putJson('/api/admin/subscription-plans/'.$id, $this->draft(['expected_version' => 1]))->assertConflict();
        $this->assertDatabaseHas('abonnements', ['id' => $id, 'name' => 'Changed', 'lock_version' => 2]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_any_subscription_history_locks_all_billing_fields_but_allows_retirement(): void
    {
        $service = $this->mock(StripePaymentService::class);
        $service->shouldNotReceive('assertPlanPrice');
        $service->shouldNotReceive('cancelSubscription');
        foreach (['pending', 'active', 'cancelled', 'expired'] as $status) {
            $plan = Abonnement::create($this->draft(['is_active' => true, 'stripe_price_id' => 'price_existing']));
            $subscription = UserSubscription::factory()->create(['subscription_plan_id' => $plan->id, 'status' => $status]);
            foreach (['price' => '299.00', 'billing_cycle' => 'yearly', 'stripe_price_id' => 'price_new'] as $field => $value) {
                $this->putJson('/api/admin/subscription-plans/'.$plan->id, $this->draft(['is_active' => true, 'stripe_price_id' => 'price_existing', 'expected_version' => 0, $field => $value]))->assertConflict();
            }
            $this->putJson('/api/admin/subscription-plans/'.$plan->id, $this->draft(['name' => 'Retired', 'stripe_price_id' => 'price_existing', 'expected_version' => 0]))->assertOk()->assertJsonPath('data.subscriptions_count', 1);
            $this->assertSame($status, $subscription->fresh()->status);
            $this->assertSame('199.00', $plan->fresh()->price);
            $this->assertFalse($plan->fresh()->is_active);
        }
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_activation_requires_provider_verification_and_sanitizes_failures(): void
    {
        $this->postJson('/api/admin/subscription-plans', $this->draft(['is_active' => true]))->assertUnprocessable()->assertJsonValidationErrors('stripe_price_id');
        $service = $this->mock(StripePaymentService::class);
        $service->shouldReceive('assertPlanPrice')->once()->andThrow(new \RuntimeException('secret provider diagnostic'));
        $this->postJson('/api/admin/subscription-plans', $this->draft(['is_active' => true, 'stripe_price_id' => 'price_bad']))->assertUnprocessable()->assertDontSee('secret provider diagnostic');
        $service->shouldReceive('assertPlanPrice')->once()->withArgs(fn ($plan) => $plan->price === '199.00' && $plan->stripe_price_id === 'price_good')->andReturnNull();
        $this->postJson('/api/admin/subscription-plans', $this->draft(['is_active' => true, 'stripe_price_id' => 'price_good']))->assertCreated();
        $this->assertDatabaseCount('abonnements', 1);
        $this->getJson('/api/subscriptions/plans')->assertOk()->assertJsonPath('data.0.checkout_available', true);
    }

    public function test_plan_changes_roll_back_if_audit_fails(): void
    {
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->postJson('/api/admin/subscription-plans', $this->draft())->assertStatus(500);
            $this->assertDatabaseCount('abonnements', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_retirement_between_validation_and_checkout_does_not_create_a_subscription(): void
    {
        $plan = Abonnement::create($this->draft(['is_active' => true, 'stripe_price_id' => 'price_existing']));
        $this->mock(StripePaymentService::class)->shouldNotReceive('processPayment');
        User::retrieved(function () use ($plan) {
            Abonnement::whereKey($plan->id)->update(['is_active' => false]);
        });
        try {
            $this->postJson('/api/subscriptions/subscribe', ['plan_id' => $plan->id, 'expected_plan_version' => 0, 'payment_method_id' => 'pm_test'])->assertConflict();
            $this->assertDatabaseCount('user_subscriptions', 0);
            $this->assertDatabaseCount('payments', 0);
        } finally {
            User::flushEventListeners();
        }
    }

    public function test_checkout_rejects_a_cached_plan_version_before_creating_payment(): void
    {
        $plan = Abonnement::create($this->draft(['is_active' => true, 'stripe_price_id' => 'price_existing']));
        $this->getJson('/api/subscriptions/plans')->assertOk()->assertJsonPath('data.0.version', 0);
        $plan->forceFill(['price' => '299.00', 'lock_version' => 1])->save();
        $this->mock(StripePaymentService::class)->shouldNotReceive('processPayment');
        $this->postJson('/api/subscriptions/subscribe', ['plan_id' => $plan->id, 'expected_plan_version' => 0, 'payment_method_id' => 'pm_test'])->assertConflict();
        $this->postJson('/api/subscriptions/subscribe', ['plan_id' => $plan->id, 'payment_method_id' => 'pm_test'])->assertUnprocessable();
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_amounts_features_and_non_admin_access_are_rejected(): void
    {
        foreach (['0', '1.001', '1e2', '1000000'] as $price) {
            $this->postJson('/api/admin/subscription-plans', $this->draft(['price' => $price]))->assertUnprocessable();
        }
        $this->postJson('/api/admin/subscription-plans', $this->draft(['features' => ['Duplicate', 'Duplicate']]))->assertUnprocessable();
        $this->postJson('/api/admin/subscription-plans', $this->draft(['features' => ['named' => 'Not a list']]))->assertUnprocessable();
        $this->getJson('/api/admin/subscription-plans?per_page=101')->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create(['role' => 'patient', 'status' => 'actif', 'email_verified_at' => now()]));
        $this->getJson('/api/admin/subscription-plans')->assertForbidden();
        $this->postJson('/api/admin/subscription-plans', $this->draft())->assertForbidden();
    }
}
