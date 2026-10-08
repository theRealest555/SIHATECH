<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Rendezvous;
use App\Models\UserSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AdminReportService
{
    private function range(CarbonImmutable $start, CarbonImmutable $end, string $basis): array
    {
        return ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'timezone' => config('app.timezone'), 'date_basis' => $basis];
    }

    public function financial(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $query = Payment::where('created_at', '>=', $start)->where('created_at', '<', $end->addDay());
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($count) => (int) $count)->all();
        $currencies = (clone $query)->where('status', 'completed')
            ->selectRaw("COALESCE(NULLIF(UPPER(TRIM(currency)), ''), 'UNSPECIFIED') AS currency, COUNT(*) AS transactions, SUM(amount) AS amount, SUM(CASE WHEN user_subscription_id IS NOT NULL THEN amount ELSE 0 END) AS subscription_amount")
            ->groupByRaw("COALESCE(NULLIF(UPPER(TRIM(currency)), ''), 'UNSPECIFIED')")->orderBy('currency')->get()
            ->map(fn ($row) => ['currency' => $row->currency, 'transactions' => (int) $row->transactions, 'completed_amount' => number_format((float) $row->amount, 2, '.', ''), 'subscription_amount' => number_format((float) $row->subscription_amount, 2, '.', '')])->all();
        $now = CarbonImmutable::now();

        return [
            'range' => $this->range($start, $end, 'payment_record_created_at'),
            'payments' => ['total' => array_sum($counts), 'by_status' => $counts, 'by_currency' => $currencies],
            'subscriptions' => [
                'created_in_range' => UserSubscription::where('created_at', '>=', $start)->where('created_at', '<', $end->addDay())->count(),
                'cancelled_in_range' => UserSubscription::where('cancelled_at', '>=', $start)->where('cancelled_at', '<', $end->addDay())->count(),
                'active_now' => UserSubscription::where('status', 'active')->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->count(),
                'as_of' => $now->toIso8601String(),
            ],
        ];
    }

    public function appointments(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $query = Rendezvous::where('rendezvous.date_heure', '>=', $start)->where('rendezvous.date_heure', '<', $end->addDay());
        $counts = (clone $query)->selectRaw('statut, COUNT(*) AS total')->groupBy('statut')->pluck('total', 'statut');
        $statuses = [];
        foreach (['en_attente', 'confirmé', 'terminé', 'annulé', 'no_show'] as $status) {
            $statuses[$status] = (int) ($counts[$status] ?? 0);
        }
        $total = (int) $counts->sum();
        $statuses['other'] = $total - array_sum($statuses);
        $specialities = (clone $query)->leftJoin('doctors', 'rendezvous.doctor_id', '=', 'doctors.id')->leftJoin('specialities', 'doctors.speciality_id', '=', 'specialities.id')
            ->selectRaw("COALESCE(specialities.nom, 'Unassigned') AS name, COUNT(*) AS total")->groupBy('specialities.nom')->orderByDesc('total')->orderBy('name')->limit(10)->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->total])->all();
        $dailyCounts = (clone $query)->selectRaw('DATE(date_heure) AS day, COUNT(*) AS total')->groupByRaw('DATE(date_heure)')->pluck('total', 'day');
        $hourExpression = DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', date_heure) AS INTEGER)" : 'HOUR(date_heure)';
        $hourCounts = (clone $query)->selectRaw("$hourExpression AS hour, COUNT(*) AS total")->groupByRaw($hourExpression)->pluck('total', 'hour');
        $daily = [];
        $hourly = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $daily[] = ['date' => $day->toDateString(), 'count' => (int) ($dailyCounts[$day->toDateString()] ?? 0)];
        }
        for ($hour = 0; $hour < 24; $hour++) {
            $hourly[] = ['hour' => sprintf('%02d:00', $hour), 'count' => (int) ($hourCounts[$hour] ?? 0)];
        }

        return ['range' => $this->range($start, $end, 'scheduled_appointment_date'), 'appointments' => ['total' => $total, 'by_status' => $statuses], 'specialities' => $specialities, 'daily' => $daily, 'hourly' => $hourly];
    }

    public function csvRows(string $type, array $data): array
    {
        $rows = [['Section', 'Metric', 'Value', 'Currency'], ['Range', 'Start date', $data['range']['start_date'], ''], ['Range', 'End date (inclusive)', $data['range']['end_date'], ''], ['Range', 'Timezone', $data['range']['timezone'], ''], ['Range', 'Date basis', $data['range']['date_basis'], '']];
        if ($type === 'financial') {
            $rows[] = ['Payments', 'Total records', $data['payments']['total'], ''];
            foreach ($data['payments']['by_status'] as $status => $count) {
                $rows[] = ['Payment status', $status, $count, ''];
            }
            foreach ($data['payments']['by_currency'] as $row) {
                foreach (['transactions', 'completed_amount', 'subscription_amount'] as $metric) {
                    $rows[] = ['Completed payments', $metric, $row[$metric], $row['currency']];
                }
            }
            foreach ($data['subscriptions'] as $metric => $value) {
                $rows[] = ['Subscriptions', $metric, $value, ''];
            }
            $rows[] = ['Scope', 'Accounting limits', 'Current payment statuses by record creation date. Refunds, fees, settlement and consultation billing are not tracked.', ''];
        } else {
            $rows[] = ['Appointments', 'Total', $data['appointments']['total'], ''];
            foreach ($data['appointments']['by_status'] as $status => $count) {
                $rows[] = ['Appointment status', $status, $count, ''];
            }
            foreach ($data['specialities'] as $row) {
                $rows[] = ['Top 10 current speciality assignments', $row['name'], $row['count'], ''];
            }
            foreach ($data['daily'] as $row) {
                $rows[] = ['Daily appointments', $row['date'], $row['count'], ''];
            }
            foreach ($data['hourly'] as $row) {
                $rows[] = ['Scheduled hour', $row['hour'], $row['count'], ''];
            }
        }

        return $rows;
    }
}
