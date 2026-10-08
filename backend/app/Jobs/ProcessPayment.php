<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Notifications\PaymentSuccessNotification;
use App\Services\StripePaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function handle(StripePaymentService $stripeService): void
    {
        // Entitlement is reconciled only by a verified provider event, never by
        // merely receiving a queue job. This job only delivers confirmed receipts.
        $this->payment->refresh();
        if ($this->payment->status !== 'completed') {
            return;
        }
        $this->payment->user?->notify(new PaymentSuccessNotification($this->payment));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Receipt delivery failed', ['payment_id' => $this->payment->id]);
    }
}
