<?php

namespace App\Exports;

use Illuminate\Support\Collection;

class FinancialReportExport
{
    protected $payments;

    public function __construct($data)
    {
        $this->payments = $data['payments'] ?? collect();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nom du patient',
            'Email',
            'Montant',
            'Méthode de paiement',
            'Statut',
            'Date de paiement',
            'Abonnement',
        ];
    }

    public function collection(): Collection
    {
        return $this->payments->map(function ($payment) {
            return collect([
                $payment->id ?? '',
                optional($payment->user)->nom ?? 'N/A',
                optional($payment->user)->email ?? 'N/A',
                $payment->amount ?? 0,
                $payment->payment_method ?? 'N/A',
                $payment->status ?? 'N/A',
                isset($payment->created_at) ? $payment->created_at->format('Y-m-d') : 'N/A',
                optional(optional($payment->userSubscription)->subscriptionPlan)->name ?? 'N/A',
            ]);
        });
    }

    public function toArray(): array
    {
        return $this->collection()->toArray();
    }
}
