<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FinancialReportExport implements FromArray, WithHeadings
{
    protected $payments;

    public function __construct($data)
    {
        $this->payments = $data['payments'];
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

    public function array(): array
    {
        return $this->payments->map(function ($payment) {
            return [
                $payment->id,
                optional($payment->user)->name,
                optional($payment->user)->email,
                $payment->amount,
                $payment->payment_method,
                $payment->status,
                $payment->created_at->format('Y-m-d'),
                optional(optional($payment->userSubscription)->Abonnement)->nom ?? 'N/A',
            ];
        })->toArray();
    }
}
