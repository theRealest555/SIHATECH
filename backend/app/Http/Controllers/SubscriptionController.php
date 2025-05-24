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

    public function getPlans(): JsonResponse
    {
        // Note: This references subscription_plans table, but your migration shows 'abonnements'
        // Using Abonnement model for now, but you may need to adjust based on your actual table
        $plans = Abonnement::where('is_active', true)->get();
        return response()->json(['data' => $plans]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|in:cih_pay,paypal,stripe',
            'payment_data' => 'required|array'
        ]);

        $user = Auth::user();
        $plan = Abonnement::findOrFail($request->plan_id);

        // Create subscription
        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
            'starts_at' => now(),
            'ends_at' => $plan->billing_cycle === 'monthly' ? now()->addMonth() : now()->addYear(),
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
            return response()->json([
                'message' => 'Abonnement créé avec succès',
                'subscription' => $subscription->load('subscriptionPlan'),
                'payment' => $paymentResult['payment']
            ]);
        }

        return response()->json([
            'message' => 'Échec du paiement',
            'error' => $paymentResult['error']
        ], 400);
    }
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
