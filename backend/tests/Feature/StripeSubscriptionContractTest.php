<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

class StripeSubscriptionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_uses_basil_confirmation_secret_and_checks_the_real_price(): void
    {
        $plan = Abonnement::factory()->create(['price' => '199.00', 'billing_cycle' => 'monthly']);
        $user = User::factory()->create();
        $user->forceFill(['stripe_customer_id' => 'cus_test'])->save();
        config(['services.stripe.secret' => 'sk_test_contract']);
        $client = new class implements ClientInterface
        {
            public array $requests = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->requests[] = [$method, $absUrl, $params];
                if (str_contains($absUrl, '/customers/')) {
                    $data = ['id' => 'cus_test', 'object' => 'customer'];
                } elseif (str_contains($absUrl, '/prices/')) {
                    $data = ['id' => 'price_test', 'object' => 'price', 'active' => true, 'currency' => 'mad', 'unit_amount' => 19900, 'recurring' => ['interval' => 'month', 'interval_count' => 1]];
                } else {
                    $data = ['id' => 'sub_test', 'object' => 'subscription', 'latest_invoice' => ['id' => 'in_test', 'object' => 'invoice', 'confirmation_secret' => ['client_secret' => 'pi_contract_secret']]];
                }

                return [json_encode($data), 200, []];
            }
        };
        ApiRequestor::setHttpClient($client);
        try {
            $result = app(StripePaymentService::class)->createSubscription(['user' => $user, 'user_id' => $user->id, 'price_id' => 'price_untrusted', 'subscription_plan_id' => $plan->id, 'subscription_id' => 7, 'payment_method_id' => 'pm_contract']);
            $this->assertTrue($result['success']);
            $this->assertSame('pi_contract_secret', $result['client_secret']);
            $request = $client->requests[2][2];
            $this->assertSame(['latest_invoice.confirmation_secret'], $request['expand']);
            $this->assertSame('pm_contract', $request['default_payment_method']);
            $this->assertSame($plan->stripe_price_id, $request['items'][0]['price']);
            $plan->update(['price' => '299.00']);
            $this->assertFalse(app(StripePaymentService::class)->createSubscription(['user' => $user, 'user_id' => $user->id, 'price_id' => $plan->stripe_price_id, 'subscription_plan_id' => $plan->id, 'subscription_id' => 8, 'payment_method_id' => 'pm_contract'])['success']);
            $this->assertCount(5, $client->requests); // Second attempt stopped before subscription creation.
        } finally {
            ApiRequestor::setHttpClient(CurlClient::instance());
        }
    }

    public function test_plan_verification_rejects_inactive_wrong_currency_amount_cycle_and_metered_prices(): void
    {
        config(['services.stripe.secret' => 'sk_test_contract']);
        $plan = Abonnement::factory()->create(['price' => '199.00', 'billing_cycle' => 'semi-annual']);
        $client = new class implements ClientInterface
        {
            public array $price;

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                return [json_encode($this->price), 200, []];
            }
        };
        $valid = ['id' => 'price_test', 'object' => 'price', 'active' => true, 'currency' => 'mad', 'unit_amount' => 19900, 'recurring' => ['interval' => 'month', 'interval_count' => 6, 'usage_type' => 'licensed']];
        ApiRequestor::setHttpClient($client);
        try {
            $client->price = $valid;
            app(StripePaymentService::class)->assertPlanPrice($plan);
            foreach ([['active' => false], ['currency' => 'usd'], ['unit_amount' => 19901], ['recurring' => null], ['recurring' => ['interval' => 'year', 'interval_count' => 6]], ['recurring' => ['interval' => 'month', 'interval_count' => 1]], ['recurring' => ['interval' => 'month', 'interval_count' => 6, 'usage_type' => 'metered']]] as $changes) {
                $client->price = array_replace($valid, $changes);
                try {
                    app(StripePaymentService::class)->assertPlanPrice($plan);
                    $this->fail('Invalid provider price should be rejected');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('Provider price does not match the published plan.', $exception->getMessage());
                }
            }
        } finally {
            ApiRequestor::setHttpClient(CurlClient::instance());
        }
    }

    public function test_subscription_status_event_cannot_activate_an_unpaid_subscription(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_contract']);
        $subscription = UserSubscription::factory()->create(['status' => 'pending', 'payment_method' => ['stripe_subscription_id' => 'sub_test']]);
        $payload = json_encode(['id' => 'evt_unpaid_active', 'object' => 'event', 'created' => time(), 'type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_test', 'object' => 'subscription', 'status' => 'active']]]);
        $time = time();
        $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, 'whsec_contract');
        $this->assertTrue(app(StripePaymentService::class)->handleWebhook($payload, $signature)['success']);
        $this->assertSame('pending', $subscription->fresh()->status);
    }
}
