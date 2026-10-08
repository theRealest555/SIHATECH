<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Customer;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent; // Added this
use Stripe\PaymentMethod; // Added this
use Stripe\Price;
use Stripe\SetupIntent;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;

class StripePaymentService
{
    protected $stripe;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe customer for a user
     */
    public function createCustomer(array $userData): Customer
    {
        try {
            return Customer::create([
                'email' => $userData['email'],
                'name' => $userData['name'] ?? null,
                'metadata' => [
                    'user_id' => $userData['user_id'] ?? null,
                ],
            ], ['idempotency_key' => 'customer_'.$userData['user_id']]);
        } catch (Exception $e) {
            Log::error('Stripe customer creation failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a Stripe SetupIntent for a customer.
     */
    public function createStripeSetupIntent(string $customerId): SetupIntent
    {
        try {
            return SetupIntent::create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
            ]);
        } catch (Exception $e) {
            Log::error('Stripe SetupIntent creation failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Update default payment method for a Stripe customer and their subscription.
     */
    public function updateSubscriptionPaymentMethod($user, ?string $stripeSubscriptionId, string $paymentMethodId): array
    {
        try {
            $customer = $this->getOrCreateCustomer($user);

            // Attach the new payment method to the customer
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $customer->id]);

            // Set as default for customer's invoices (for future subscriptions or direct invoices)
            Customer::update($customer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            // If there's an active Stripe subscription, update its default payment method too
            if ($stripeSubscriptionId) {
                Subscription::update($stripeSubscriptionId, [
                    'default_payment_method' => $paymentMethodId,
                ]);
            }

            return ['success' => true];
        } catch (Exception $e) {
            Log::error('Stripe payment method update failed: '.$e->getMessage(), [
                'user_id' => $user->id,
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a payment intent for one-time payment
     */
    public function createPaymentIntent(array $data): array
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $this->convertToStripeAmount($data['amount'], $data['currency']),
                'currency' => strtolower($data['currency']),
                'metadata' => [
                    'user_id' => $data['user_id'] ?? null,
                    'subscription_id' => $data['subscription_id'] ?? null,
                ],
                'description' => $data['description'] ?? 'SIHATECH Payment',
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ];
        } catch (Exception $e) {
            Log::error('Stripe payment intent creation failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a subscription
     */
    public function assertPlanPrice(Abonnement $plan): void
    {
        $price = Price::retrieve($plan->stripe_price_id);
        $interval = $plan->billing_cycle === 'yearly' ? 'year' : 'month';
        $count = $plan->billing_cycle === 'semi-annual' ? 6 : 1;
        if (! $price->active || $price->currency !== 'mad' || $price->unit_amount !== (int) round((float) $plan->price * 100)
            || $price->recurring?->interval !== $interval || $price->recurring?->interval_count !== $count
            || ($price->recurring?->usage_type ?? 'licensed') !== 'licensed') {
            throw new \RuntimeException('Provider price does not match the published plan.');
        }
    }

    public function createSubscription(array $data): array
    {
        try {
            // Create or retrieve customer
            $customer = $this->getOrCreateCustomer($data['user']);

            $plan = Abonnement::findOrFail($data['subscription_plan_id']);
            $this->assertPlanPrice($plan);
            // Create subscription
            $subscription = Subscription::create([
                'customer' => $customer->id,
                'items' => [
                    ['price' => $plan->stripe_price_id],
                ],
                'payment_behavior' => 'default_incomplete',
                'default_payment_method' => $data['payment_method_id'],
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.confirmation_secret'],
                'metadata' => [
                    'user_id' => $data['user_id'],
                    'subscription_plan_id' => $data['subscription_plan_id'],
                    'local_subscription_id' => $data['subscription_id'],
                ],
            ], ['idempotency_key' => 'subscription_'.$data['subscription_id']]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'client_secret' => $subscription->latest_invoice->confirmation_secret->client_secret ?? null,
            ];
        } catch (Exception $e) {
            Log::error('Stripe subscription creation failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);
            $subscription->cancel();

            return [
                'success' => true,
                'subscription' => $subscription,
            ];
        } catch (Exception $e) {
            Log::error('Stripe subscription cancellation failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process payment and update database
     */
    public function processPayment(array $data): array
    {
        $transactionId = $this->generateTransactionId();

        try {
            // Create payment record
            $payment = Payment::create([
                'user_id' => $data['user_id'],
                'user_subscription_id' => $data['subscription_id'] ?? null,
                'transaction_id' => $transactionId,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'MAD',
                'status' => 'pending',
                'payment_method' => 'stripe',
                'payment_data' => [
                    'stripe_payment_intent_id' => $data['payment_intent_id'] ?? null,
                    'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
                ],
            ]);

            // For subscription payments, handle differently
            if (isset($data['subscription_id']) && isset($data['price_id'])) {
                $subscriptionResult = $this->createSubscription([
                    'user' => $data['user'],
                    'user_id' => $data['user_id'],
                    'price_id' => $data['price_id'],
                    'subscription_plan_id' => $data['subscription_plan_id'],
                    'subscription_id' => $data['subscription_id'],
                    'payment_method_id' => $data['payment_method_id'],
                ]);

                if ($subscriptionResult['success']) {
                    $payment->update([
                        'payment_data' => array_merge($payment->payment_data, [
                            'stripe_subscription_id' => $subscriptionResult['subscription']->id,
                        ]),
                    ]);

                    return [
                        'success' => true,
                        'payment' => $payment,
                        'client_secret' => $subscriptionResult['client_secret'],
                        'subscription' => $subscriptionResult['subscription'],
                    ];
                } else {
                    $payment->update(['status' => 'failed']);

                    return $subscriptionResult;
                }
            } else {
                // One-time payment
                $paymentIntentResult = $this->createPaymentIntent($data);

                if ($paymentIntentResult['success']) {
                    $payment->update([
                        'payment_data' => array_merge($payment->payment_data, [
                            'stripe_payment_intent_id' => $paymentIntentResult['payment_intent_id'],
                        ]),
                    ]);

                    return [
                        'success' => true,
                        'payment' => $payment,
                        'client_secret' => $paymentIntentResult['client_secret'],
                    ];
                } else {
                    $payment->update(['status' => 'failed']);

                    return $paymentIntentResult;
                }
            }
        } catch (Exception $e) {
            if (isset($payment)) {
                $payment->update(['status' => 'failed']);
            }

            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'user_id' => $data['user_id'] ?? null,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment' => $payment ?? null,
            ];
        }
    }

    /**
     * Handle webhook events from Stripe
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );

            return DB::transaction(function () use ($event) {
                $events = DB::table('stripe_events');
                $events->insertOrIgnore(['id' => $event->id, 'type' => $event->type, 'created' => $event->created]);
                $stored = $events->where('id', $event->id)->lockForUpdate()->first();
                if ($stored->processed_at !== null) {
                    return ['success' => true];
                }
                switch ($event->type) {
                    case 'payment_intent.succeeded':
                        $this->handlePaymentIntentSucceeded($event->data->object);
                        break;

                    case 'payment_intent.payment_failed':
                        $this->handlePaymentIntentFailed($event->data->object);
                        break;

                    case 'customer.subscription.created':
                        $this->handleSubscriptionCreated($event->data->object);
                        break;

                    case 'customer.subscription.updated':
                        $this->handleSubscriptionUpdated($event->data->object);
                        break;

                    case 'customer.subscription.deleted':
                        $this->handleSubscriptionDeleted($event->data->object);
                        break;

                    case 'invoice.payment_succeeded':
                        $this->handleInvoicePaymentSucceeded($event->data->object);
                        break;

                    case 'invoice.payment_failed':
                        $this->handleInvoicePaymentFailed($event->data->object);
                        break;

                    default:
                        Log::info('Unhandled webhook event type: '.$event->type);
                }

                DB::table('stripe_events')->where('id', $event->id)->update(['processed_at' => now()]);

                return ['success' => true, 'message' => 'Webhook handled successfully'];
            }, 3);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: '.$e->getMessage());

            return ['success' => false, 'error' => 'Invalid signature', 'status' => 400];
        } catch (Exception $e) {
            Log::error('Stripe webhook handling failed: '.$e->getMessage());

            return ['success' => false, 'error' => 'Webhook processing failed', 'status' => 500];
        }
    }

    /**
     * Get or create Stripe customer for a user
     */
    public function getOrCreateCustomer($user): Customer
    {
        return DB::transaction(function () use ($user) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($lockedUser->stripe_customer_id) {
                // Network/provider failures must not silently create a second customer.
                return Customer::retrieve($lockedUser->stripe_customer_id);
            }
            $customer = $this->createCustomer([
                'email' => $lockedUser->email,
                'name' => $lockedUser->prenom.' '.$lockedUser->nom,
                'user_id' => $lockedUser->id,
            ]);
            $lockedUser->forceFill(['stripe_customer_id' => $customer->id])->save();

            return $customer;
        });
    }

    /**
     * Handle successful payment intent
     */
    protected function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $payment = Payment::where('payment_data->stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'payment_data' => array_merge($payment->payment_data, [
                    'stripe_charge_id' => $paymentIntent->charges->data[0]->id ?? null,
                ]),
            ]);

            // If this is a subscription payment, activate the subscription
            if ($payment->user_subscription_id) {
                $subscription = UserSubscription::whereKey($payment->user_subscription_id)->lockForUpdate()->first();
                if ($subscription && $subscription->status === 'pending') {
                    $subscription->update(['status' => 'active']);
                }
            }
        }
    }

    /**
     * Handle failed payment intent
     */
    protected function handlePaymentIntentFailed($paymentIntent): void
    {
        $payment = Payment::where('payment_data->stripe_payment_intent_id', $paymentIntent->id)->first();

        if ($payment && $payment->status !== 'completed') {
            $payment->update([
                'status' => 'failed',
                'payment_data' => array_merge($payment->payment_data, [
                    'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
                ]),
            ]);
        }
    }

    /**
     * Handle subscription created
     */
    protected function handleSubscriptionCreated($subscription): void
    {
        Log::info('Subscription created: '.$subscription->id, [
            'customer' => $subscription->customer,
            'metadata' => $subscription->metadata,
        ]);
    }

    /**
     * Handle subscription updated
     */
    protected function handleSubscriptionUpdated($subscription): void
    {
        $userSubscription = UserSubscription::where('payment_method->stripe_subscription_id', $subscription->id)->first();

        if ($userSubscription) {
            $status = $this->mapStripeStatus($subscription->status);
            if ($status === 'active') {
                return; // Only paid invoice/payment events grant access.
            }
            if ($userSubscription->status === 'cancelled' && $status !== 'cancelled') {
                return;
            }
            $userSubscription->update(['status' => $status]);
        }
    }

    /**
     * Handle subscription deleted
     */
    protected function handleSubscriptionDeleted($subscription): void
    {
        $userSubscription = UserSubscription::where('payment_method->stripe_subscription_id', $subscription->id)->first();

        if ($userSubscription) {
            $userSubscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }
    }

    /**
     * Handle invoice payment succeeded
     */
    protected function handleInvoicePaymentSucceeded($invoice): void
    {
        $stripeId = $invoice->subscription ?? $invoice->parent?->subscription_details?->subscription;
        if (! $stripeId) {
            return;
        }
        $subscription = UserSubscription::where('payment_method->stripe_subscription_id', $stripeId)->lockForUpdate()->first();
        if (! $subscription) {
            throw new \RuntimeException('Subscription mapping is not available yet.');
        }
        // The event is already signature-verified; a paid invoice establishes entitlement.
        $payment = Payment::where('user_subscription_id', $subscription->id)->where('status', 'pending')->first();
        $attributes = [
            'user_id' => $subscription->user_id, 'user_subscription_id' => $subscription->id,
            'transaction_id' => 'stripe_'.$invoice->id, 'amount' => $invoice->amount_paid / 100,
            'currency' => strtoupper($invoice->currency), 'status' => 'completed', 'payment_method' => 'stripe',
            'payment_data' => ['stripe_invoice_id' => $invoice->id, 'stripe_subscription_id' => $stripeId],
        ];
        if ($payment) {
            $payment->update($attributes);
        } else {
            Payment::firstOrCreate(['transaction_id' => $attributes['transaction_id']], $attributes);
        }
        $periodEnd = $invoice->lines->data[0]->period->end ?? null;
        if ($periodEnd && $subscription->status !== 'cancelled') {
            $periodEnd = max($periodEnd, $subscription->ends_at->timestamp);
            $subscription->update(['status' => 'active', 'ends_at' => Carbon::createFromTimestamp($periodEnd)]);
        }
    }

    /**
     * Handle invoice payment failed
     */
    protected function handleInvoicePaymentFailed($invoice): void
    {
        Log::error('Invoice payment failed', [
            'invoice_id' => $invoice->id,
            'subscription' => $invoice->subscription,
        ]);

        if ($invoice->subscription) {
            $userSubscription = UserSubscription::where('payment_method->stripe_subscription_id', $invoice->subscription)->first();

            if ($userSubscription) {
                // You might want to send a notification to the user
                // or mark the subscription as at risk
                Log::warning('Subscription payment failed for user: '.$userSubscription->user_id);
            }
        }
    }

    /**
     * Map Stripe subscription status to our status
     */
    protected function mapStripeStatus(string $stripeStatus): string
    {
        $statusMap = [
            'active' => 'active',
            'past_due' => 'pending', // Still active but payment failed
            'unpaid' => 'pending',
            'canceled' => 'cancelled',
            'incomplete' => 'pending',
            'incomplete_expired' => 'cancelled',
            'trialing' => 'active',
        ];

        return $statusMap[$stripeStatus] ?? 'pending';
    }

    /**
     * Convert amount to Stripe format (cents)
     */
    protected function convertToStripeAmount(float $amount, string $currency): int
    {
        // For most currencies, multiply by 100 to convert to cents
        // Some currencies don't have decimal places
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return (int) $amount;
        }

        return (int) ($amount * 100);
    }

    /**
     * Generate unique transaction ID
     */
    protected function generateTransactionId(): string
    {
        return 'TXN_'.now()->format('YmdHis').'_'.Str::random(6);
    }
}
