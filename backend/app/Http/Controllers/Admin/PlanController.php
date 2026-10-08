<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\AuditLog;
use App\Services\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    private function present(Abonnement $plan): array
    {
        $features = $plan->features;
        if (is_string($features)) {
            $features = json_decode($features, true);
        }
        $valid = is_array($features) && array_is_list($features) && count(array_filter($features, 'is_string')) === count($features);

        return ['id' => $plan->id, 'name' => $plan->name, 'description' => $plan->description ?? '', 'price' => $plan->price, 'billing_cycle' => $plan->billing_cycle, 'features' => $valid ? $features : [], 'features_need_review' => ! $valid, 'is_active' => $plan->is_active, 'stripe_price_id' => $plan->stripe_price_id, 'version' => (int) $plan->lock_version, 'subscriptions_count' => (int) ($plan->user_subscriptions_count ?? $plan->userSubscriptions()->count())];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['page' => 'nullable|integer|min:1|max:100000', 'per_page' => 'nullable|integer|min:1|max:100']);
        $page = Abonnement::withCount('userSubscriptions')->orderByDesc('id')->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => $page->getCollection()->map(fn ($plan) => $this->present($plan)), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id)
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?int $id = null)
    {
        $rules = ['name' => 'required|string|max:100', 'description' => 'nullable|string|max:2000', 'price' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'regex:/^\d{1,6}(\.\d{1,2})?$/'], 'billing_cycle' => 'required|in:monthly,semi-annual,yearly', 'features' => 'present|array|list|max:20', 'features.*' => 'required|string|max:255|distinct', 'is_active' => 'required|boolean', 'stripe_price_id' => 'nullable|string|max:255|regex:/^price_[A-Za-z0-9_]+$/'];
        if ($id !== null) {
            $rules['expected_version'] = 'required|integer|min:0';
        }
        $data = $request->validate($rules);
        $plan = DB::transaction(function () use ($request, $id, $data) {
            $plan = $id !== null ? Abonnement::whereKey($id)->lockForUpdate()->firstOrFail() : new Abonnement;
            abort_if($id !== null && (int) $plan->lock_version !== (int) $data['expected_version'], 409, 'This plan changed. Refresh before editing again.');
            $oldActive = $plan->exists && $plan->is_active;
            $used = $plan->exists && $plan->userSubscriptions()->exists();
            $plan->fill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'price' => $data['price'], 'billing_cycle' => $data['billing_cycle'], 'features' => $data['features'], 'is_active' => $data['is_active'], 'stripe_price_id' => $data['stripe_price_id'] ?? null]);
            $billingChanged = $plan->isDirty(['price', 'billing_cycle', 'stripe_price_id']);
            abort_if($used && $billingChanged, 409, 'This plan has subscription history. Create a new plan for a different price, billing cycle or Stripe price.');
            if ($plan->is_active && (! $oldActive || $billingChanged)) {
                if (! filled($plan->stripe_price_id)) {
                    throw ValidationException::withMessages(['stripe_price_id' => 'An active plan requires a verified Stripe price.']);
                }
                try {
                    app(StripePaymentService::class)->assertPlanPrice($plan);
                } catch (\Exception) {
                    throw ValidationException::withMessages(['stripe_price_id' => 'Could not verify an active MAD recurring Stripe price matching this amount and billing cycle. Check the Stripe configuration and price.']);
                }
            }
            if ($plan->exists && ! $plan->isDirty()) {
                return $plan;
            }
            $plan->lock_version = (int) ($plan->lock_version ?? 0) + 1;
            $plan->save();
            AuditLog::create(['user_id' => $request->user()->id, 'action' => $id !== null ? 'updated_subscription_plan' : 'created_subscription_plan', 'target_type' => Abonnement::class, 'target_id' => $plan->id]);

            return $plan;
        }, 3);

        return response()->json(['data' => $this->present($plan)], $id !== null ? 200 : 201);
    }
}
