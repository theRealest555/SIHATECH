<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\UserSubscription;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Exception;

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
            ]);
        } catch (Exception $e) {
            Log::error('Stripe customer creation failed: ' . $e->getMessage());
            throw $e;
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
            Log::error('Stripe payment intent creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a subscription
     */
    public function createSubscription(array $data): array
    {
        try {
            // Create or retrieve customer
            $customer = $this->getOrCreateCustomer($data['user']);

            // Create subscription
            $subscription = Subscription::create([
                'customer' => $customer->id,
                'items' => [
                    ['price' => $data['price_id']],
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'user_id' => $data['user_id'],
                    'subscription_plan_id' => $data['subscription_plan_id'],
                ],
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret,
            ];
        } catch (Exception $e) {
            Log::error('Stripe subscription creation failed: ' . $e->getMessage());
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
            Log::error('Stripe subscription cancellation failed: ' . $e->getMessage());
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
                ]
            ]);

            // For subscription payments, handle differently
            if (isset($data['subscription_id']) && isset($data['price_id'])) {
                $subscriptionResult = $this->createSubscription([
                    'user' => $data['user'],
                    'user_id' => $data['user_id'],
                    'price_id' => $data['price_id'],
                    'subscription_plan_id' => $data['subscription_plan_id'],
                ]);

                if ($subscriptionResult['success']) {
                    $payment->update([
                        'payment_data' => array_merge($payment->payment_data, [
                            'stripe_subscription_id' => $subscriptionResult['subscription']->id,
                        ])
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
                        ])
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
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment' => $payment ?? null
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

            // Handle the event
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
                    Log::info('Unhandled webhook event type: ' . $event->type);
            }

            return ['success' => true, 'message' => 'Webhook handled successfully'];
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Invalid signature'];
        } catch (Exception $e) {
            Log::error('Stripe webhook handling failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get or create Stripe customer for a user
     */
    public function getOrCreateCustomer($user): Customer
    {
        // Check if user already has a Stripe customer ID
        if ($user->stripe_customer_id) {
            try {
                return Customer::retrieve($user->stripe_customer_id);
            } catch (Exception $e) {
                Log::warning('Failed to retrieve Stripe customer: ' . $e->getMessage());
            }
        }

        // Create new customer
        $customer = $this->createCustomer([
            'email' => $user->email,
            'name' => $user->prenom . ' ' . $user->nom,
            'user_id' => $user->id,
        ]);

        // Save customer ID to user
        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
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
                ])
            ]);

            // If this is a subscription payment, activate the subscription
            if ($payment->user_subscription_id) {
                $subscription = UserSubscription::find($payment->user_subscription_id);
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

        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'payment_data' => array_merge($payment->payment_data, [
                    'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
                ])
            ]);
        }
    }

    /**
     * Handle subscription created
     */
    protected function handleSubscriptionCreated($subscription): void
    {
        Log::info('Subscription created: ' . $subscription->id, [
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
        // Create payment record for subscription renewal
        if ($invoice->subscription && $invoice->billing_reason === 'subscription_cycle') {
            $userSubscription = UserSubscription::where('payment_method->stripe_subscription_id', $invoice->subscription)->first();

            if ($userSubscription) {
                Payment::create([
                    'user_id' => $userSubscription->user_id,
                    'user_subscription_id' => $userSubscription->id,
                    'transaction_id' => 'stripe_' . $invoice->id,
                    'amount' => $invoice->amount_paid / 100, // Convert from cents
                    'currency' => strtoupper($invoice->currency),
                    'status' => 'completed',
                    'payment_method' => 'stripe',
                    'payment_data' => [
                        'stripe_invoice_id' => $invoice->id,
                        'stripe_charge_id' => $invoice->charge,
                    ]
                ]);

                // Extend subscription end date
                $userSubscription->update([
                    'ends_at' => now()->addMonth(), // Adjust based on billing cycle
                ]);
            }
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
                Log::warning('Subscription payment failed for user: ' . $userSubscription->user_id);
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
            'past_due' => 'active', // Still active but payment failed
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
        return 'TXN_' . now()->format('YmdHis') . '_' . Str::random(6);
    }
}
