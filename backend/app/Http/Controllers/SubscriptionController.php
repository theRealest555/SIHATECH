<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\UserSubscription;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get available subscription plans
     */
    public function getPlans(): JsonResponse
    {
        // Use the Abonnement model with the correct table name
        $plans = Abonnement::where('is_active', true)->get();

        return response()->json(['data' => $plans]);
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:abonnements,id',
            'payment_method' => 'required|in:cih_pay,paypal,stripe',
            'payment_data' => 'required|array'
        ]);

        $user = Auth::user();

        // Get plan from Abonnement model
        $plan = Abonnement::findOrFail($request->plan_id);

        // Calculate end date based on billing cycle
        $endsAt = $plan->billing_cycle === 'monthly' ? now()->addMonth() : now()->addYear();

        // Create subscription
        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'payment_method' => $request->payment_data
        ]);

        // Process payment
        $paymentResult = $this->paymentService->processPayment([
            'amount' => $plan->price,
            'currency' => 'MAD',
            'payment_method' => $request->payment_method,
            'payment_data' => $request->payment_data,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id
        ]);

        if ($paymentResult['success']) {
            $subscription->update(['status' => 'active']);

            // Load the subscription with plan data
            $subscription->load('subscriptionPlan');

            return response()->json([
                'message' => 'Abonnement créé avec succès',
                'subscription' => $subscription,
                'payment' => $paymentResult['payment']
            ]);
        }

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Échec du paiement',
            'error' => $paymentResult['error']
        ], 400);
    }

    /**
     * Cancel user subscription
     */
    public function cancelSubscription(): JsonResponse
    {
        $user = Auth::user();
        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'Aucun abonnement actif trouvé'], 404);
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        return response()->json(['message' => 'Abonnement annulé avec succès']);
    }

    /**
     * Get user's current subscription
     */
    public function getUserSubscription(): JsonResponse
    {
        $user = Auth::user();
        $subscription = UserSubscription::with('subscriptionPlan')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        return response()->json(['data' => $subscription]);
    }
}
