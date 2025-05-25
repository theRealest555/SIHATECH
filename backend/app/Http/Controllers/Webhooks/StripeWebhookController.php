<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    protected $stripeService;

    public function __construct(StripePaymentService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Handle incoming Stripe webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$signature) {
            Log::error('Stripe webhook received without signature');
            return response()->json(['error' => 'Missing signature'], 400);
        }

        $result = $this->stripeService->handleWebhook($payload, $signature);

        if ($result['success']) {
            return response()->json(['status' => 'success'], 200);
        }

        return response()->json(['error' => $result['error']], 400);
    }
}
