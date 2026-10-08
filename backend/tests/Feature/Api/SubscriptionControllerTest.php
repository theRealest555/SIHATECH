<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessPayment;
use App\Models\Abonnement;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_creation_does_not_grant_paid_access(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'status' => 'actif']);
        $plan = Abonnement::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);
        $service = $this->mock(StripePaymentService::class);
        $service->shouldReceive('processPayment')->once()->andReturn([
            'success' => true, 'subscription' => (object) ['id' => 'sub_test'],
            'payment' => null, 'client_secret' => 'secret_test',
        ]);
        $this->postJson('/api/subscriptions/subscribe', ['plan_id' => $plan->id, 'expected_plan_version' => 0, 'payment_method_id' => 'pm_test'])
            ->assertOk()->assertJsonPath('data.subscription.status', 'pending');
        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_queue_job_cannot_complete_an_unpaid_payment(): void
    {
        Notification::fake();
        $subscription = UserSubscription::factory()->create(['status' => 'pending']);
        $payment = Payment::factory()->create(['user_subscription_id' => $subscription->id, 'status' => 'pending']);
        (new ProcessPayment($payment))->handle(app(StripePaymentService::class));
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('pending', $subscription->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_signed_invoice_webhooks_are_idempotent_and_use_the_provider_period(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_regression']);
        $subscription = UserSubscription::factory()->create(['status' => 'pending', 'payment_method' => ['stripe_subscription_id' => 'sub_test']]);
        Payment::factory()->create(['user_subscription_id' => $subscription->id, 'status' => 'pending']);
        $end = now()->addYear()->startOfSecond()->timestamp;
        $event = ['id' => 'evt_paid', 'object' => 'event', 'created' => time(), 'type' => 'invoice.payment_succeeded', 'data' => ['object' => [
            'id' => 'in_paid', 'object' => 'invoice', 'subscription' => 'sub_test', 'amount_paid' => 12000, 'currency' => 'mad',
            'lines' => ['data' => [['period' => ['end' => $end]]]],
        ]]];
        $service = app(StripePaymentService::class);
        foreach (['evt_paid', 'evt_paid', 'evt_duplicate_invoice'] as $id) {
            $event['id'] = $id;
            $payload = json_encode($event);
            $time = time();
            $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, 'whsec_regression');
            $this->assertTrue($service->handleWebhook($payload, $signature)['success']);
        }
        $this->assertSame(1, Payment::where('user_subscription_id', $subscription->id)->count());
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame($end, $subscription->fresh()->ends_at->timestamp);
    }
}
