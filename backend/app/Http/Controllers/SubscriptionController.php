<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

// Remove direct Stripe SDK imports if all calls are through the service
// use Stripe\SetupIntent;
// use Stripe\PaymentMethod;

class SubscriptionController extends Controller
{
    protected $stripeService;

    public function __construct(StripePaymentService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Get available subscription plans
     */
    public function getPlans(): JsonResponse
    {
        $plans = Abonnement::where('is_active', true)->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'version' => (int) $plan->lock_version,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'features' => $plan->features,
                'currency' => 'MAD',
                'checkout_available' => filled($plan->stripe_price_id),
                'popular' => $plan->name === 'Premium',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $plans,
            'meta' => ['stripe_publishable_key' => config('services.stripe.key'), 'checkout_enabled' => filled(config('services.stripe.key')) && filled(config('services.stripe.secret')) && filled(config('services.stripe.webhook_secret'))],
        ]);
    }

    /**
     * Get Stripe setup intent for payment method collection
     */
    public function getSetupIntent(): JsonResponse
    {
        try {
            $user = Auth::user();
            $customer = $this->stripeService->getOrCreateCustomer($user);

            // Use the service to create the SetupIntent
            $setupIntent = $this->stripeService->createStripeSetupIntent($customer->id); // Assuming this method exists in your service

            return response()->json([
                'status' => 'success',
                'client_secret' => $setupIntent->client_secret,
                'customer_id' => $customer->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Setup intent creation failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to initialize payment setup',
            ], 500);
        }
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required', Rule::exists('abonnements', 'id')->where('is_active', true)],
            'expected_plan_version' => ['required', 'integer', 'min:0'],
            'payment_method_id' => ['required', 'string', 'regex:/^pm_[A-Za-z0-9_]+$/', 'max:255'],
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $user = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                $plan = Abonnement::whereKey($request->plan_id)->lockForUpdate()->firstOrFail();
                if (! $plan->is_active) {
                    return response()->json(['message' => 'This plan is no longer available for new subscriptions.'], 409);
                }
                if ((int) $plan->lock_version !== (int) $request->expected_plan_version) {
                    return response()->json(['message' => 'This plan changed. Refresh the plans and review the current price before subscribing.'], 409);
                }

                $existingSubscription = UserSubscription::where('user_id', $user->id)
                    ->whereIn('status', ['active', 'pending'])->first();

                if ($existingSubscription) {
                    return response()->json(['message' => 'An active or pending subscription already exists. Check your subscription before trying again.'], 409);
                }

                if (! filled($plan->stripe_price_id)) {
                    return response()->json(['message' => 'This plan is not ready for payment.'], 503);
                }
                $startsAt = now();
                $endsAt = $plan->billing_cycle === 'monthly'
                    ? $startsAt->copy()->addMonthNoOverflow()
                    : ($plan->billing_cycle === 'semi-annual' ? $startsAt->copy()->addMonthsNoOverflow(6) : $startsAt->copy()->addYearNoOverflow());

                $subscription = UserSubscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => 'pending',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'payment_method' => [
                        'type' => 'stripe',
                        'payment_method_id' => $request->payment_method_id,
                    ],
                ]);

                $paymentResult = $this->stripeService->processPayment([
                    'amount' => $plan->price,
                    'currency' => 'MAD',
                    'user_id' => $user->id,
                    'user' => $user,
                    'subscription_id' => $subscription->id,
                    'subscription_plan_id' => $plan->id,
                    'price_id' => $plan->stripe_price_id,
                    'payment_method_id' => $request->payment_method_id,
                    'description' => "Abonnement {$plan->name} pour {$user->prenom} {$user->nom}",
                ]);

                if ($paymentResult['success']) {
                    $subscription->update([
                        'status' => 'pending',
                        'payment_method' => array_merge($subscription->payment_method, [
                            'stripe_subscription_id' => $paymentResult['subscription']->id ?? null,
                            'client_secret' => $paymentResult['client_secret'] ?? null,
                        ]),
                    ]);
                    $subscription->load('subscriptionPlan');

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Subscription created. Payment confirmation is required.',
                        'data' => [
                            'subscription' => $subscription,
                            'payment' => $paymentResult['payment'],
                            'client_secret' => $paymentResult['client_secret'] ?? null,
                        ],
                    ]);
                }

                // Provider failures can happen after remote creation; reconcile this pending attempt before retrying.

                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment could not be confirmed. Check your pending subscription or contact support before retrying.',
                    'error' => 'Payment provider rejected the request',
                ], 400);
            });
        } catch (\Exception $e) {
            Log::error('Subscription creation failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create subscription',
            ], 500);
        }
    }

    /**
     * Cancel user subscription
     */
    public function cancelSubscription(): JsonResponse
    {
        try {
            return DB::transaction(function () {
                $user = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                $subscription = UserSubscription::where('user_id', $user->id)
                    ->whereIn('status', ['active', 'pending'])
                    ->lockForUpdate()->first();

                if (! $subscription) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Aucun abonnement actif trouvé',
                    ], 404);
                }

                if ($subscription->status === 'pending' && empty($subscription->payment_method['stripe_subscription_id'])) {
                    return response()->json(['message' => 'Payment reconciliation is required before cancellation. Please contact support.'], 409);
                }
                if (! empty($subscription->payment_method['stripe_subscription_id'])) {
                    $result = $this->stripeService->cancelSubscription(
                        $subscription->payment_method['stripe_subscription_id']
                    );
                    if (! $result['success']) {
                        Log::error('Failed to cancel Stripe subscription', [
                            'subscription_id' => $subscription->id,
                            'error' => 'Webhook processing failed' ?? 'Unknown Stripe cancellation error',
                        ]);

                        return response()->json(['message' => 'Unable to cancel with the payment provider. Please retry.'], 502);
                    }
                }

                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Abonnement annulé avec succès',
                    'data' => [
                        'subscription' => $subscription,
                        'effective_until' => now()->toDateString(),
                    ],
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel subscription',
            ], 500);
        }
    }

    /**
     * Update payment method for subscription
     */
    public function updatePaymentMethod(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method_id' => ['required', 'string', 'regex:/^pm_[A-Za-z0-9_]+$/', 'max:255'],
        ]);

        try {
            $user = Auth::user();
            $subscription = UserSubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (! $subscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active subscription found',
                ], 404);
            }

            // Use the service to update payment method
            $result = $this->stripeService->updateSubscriptionPaymentMethod(
                $user,
                $subscription->payment_method['stripe_subscription_id'] ?? null, // Pass Stripe subscription ID if available
                $request->payment_method_id
            );

            if (! $result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update payment method with Stripe',
                    'error' => $result['error'] ?? 'Unknown error',
                ], 500);
            }

            $subscription->update([
                'payment_method' => array_merge($subscription->payment_method, [
                    'payment_method_id' => $request->payment_method_id,
                    'updated_at' => now(),
                ]),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment method updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Payment method update failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment method',
            ], 500);
        }
    }

    public function getUserSubscription(): JsonResponse
    {
        $user = Auth::user();
        $subscription = UserSubscription::with('subscriptionPlan')
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN status IN ('active', 'pending') THEN 0 ELSE 1 END")
            ->orderByDesc('id')->first();

        if (! $subscription) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'No active subscription',
            ]);
        }

        $payments = Payment::where('user_subscription_id', $subscription->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'subscription' => $subscription,
                'next_billing_date' => $subscription->status === 'active' ? $subscription->ends_at->format('Y-m-d') : null,
                'has_access' => $subscription->isActive(),
                'payment_client_secret' => $subscription->status === 'pending' ? ($subscription->payment_method['client_secret'] ?? null) : null,
                'days_remaining' => $subscription->getRemainingDays(),
                'is_expiring_soon' => $subscription->isActive() && $subscription->getRemainingDays() <= 7,
                'recent_payments' => $payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'date' => $payment->created_at->format('Y-m-d'),
                        'transaction_id' => $payment->transaction_id,
                    ];
                }),
            ],
        ]);
    }

    public function getSubscriptionHistory(): JsonResponse
    {
        $user = Auth::user();

        $subscriptions = UserSubscription::with('subscriptionPlan')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'plan_name' => $subscription->subscriptionPlan->name ?? 'Unknown',
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at->format('Y-m-d'),
                    'ends_at' => $subscription->ends_at->format('Y-m-d'),
                    'cancelled_at' => $subscription->cancelled_at ? $subscription->cancelled_at->format('Y-m-d') : null,
                    'amount' => $subscription->subscriptionPlan->price ?? 0,
                    'billing_cycle' => $subscription->subscriptionPlan->billing_cycle ?? 'monthly',
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $subscriptions,
        ]);
    }

    public function getPaymentHistory(): JsonResponse
    {
        $user = Auth::user();

        $payments = Payment::where('user_id', $user->id)
            ->with('userSubscription.subscriptionPlan')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $payments->getCollection()->transform(function ($payment) {
            return [
                'id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'date' => $payment->created_at->format('Y-m-d H:i:s'),
                'plan_name' => $payment->userSubscription && $payment->userSubscription->subscriptionPlan ? $payment->userSubscription->subscriptionPlan->name : 'One-time payment',
                'invoice_url' => $payment->payment_data['invoice_url'] ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'from' => $payments->firstItem(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'to' => $payments->lastItem(),
                'total' => $payments->total(),
            ],
        ]);
    }

    protected function cancelExistingSubscription(UserSubscription $subscription): void
    {
        if (! empty($subscription->payment_method['stripe_subscription_id'])) {
            try {
                $this->stripeService->cancelSubscription(
                    $subscription->payment_method['stripe_subscription_id']
                );
            } catch (\Exception $e) {
                Log::error('Failed to cancel existing Stripe subscription: '.$e->getMessage());
            }
        }
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
