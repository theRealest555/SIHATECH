<?php

namespace App\Services;

use App\Models\Doctor;
use Carbon\CarbonImmutable;

class DoctorStatisticsService
{
    public function __construct(protected Doctor $doctor) {}

    public function getDashboardStats(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): array
    {
        $start ??= CarbonImmutable::now()->startOfMonth();
        $end ??= CarbonImmutable::now()->endOfMonth()->startOfDay();
        // Stored appointment timestamps are clinic wall time, matching booking and schedule validation.
        $query = $this->doctor->appointments()->where('date_heure', '>=', $start->startOfDay())
            ->where('date_heure', '<', $end->addDay()->startOfDay());
        $counts = (clone $query)->selectRaw('statut, COUNT(*) AS aggregate')->groupBy('statut')->pluck('aggregate', 'statut');
        $statuses = [];
        foreach (['en_attente', 'confirmé', 'terminé', 'annulé', 'no_show'] as $status) {
            $statuses[$status] = (int) ($counts[$status] ?? 0);
        }
        $total = (int) $counts->sum();
        $statuses['other'] = $total - array_sum($statuses);
        $seen = (clone $query)->where('statut', 'terminé');
        $returning = (clone $seen)->selectRaw('patient_id, COUNT(*) AS visits')->groupBy('patient_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $daily = (clone $query)->selectRaw('DATE(date_heure) AS day, COUNT(*) AS aggregate')->groupByRaw('DATE(date_heure)')->pluck('aggregate', 'day');
        $trend = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $trend[] = ['date' => $day->toDateString(), 'count' => (int) ($daily[$day->toDateString()] ?? 0)];
        }
        $reviews = $this->doctor->approvedReviews()->where('created_at', '>=', $start->startOfDay())
            ->where('created_at', '<', $end->addDay()->startOfDay())->selectRaw('AVG(rating) AS average, COUNT(*) AS total')->first();

        return [
            'range' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'timezone' => config('app.timezone')],
            'appointments' => ['total' => $total, 'by_status' => $statuses],
            'patients' => ['total_unique' => (clone $query)->distinct()->count('patient_id'), 'seen' => (clone $seen)->distinct()->count('patient_id'), 'repeat_completed' => $returning],
            'rating' => ['average' => $reviews->total ? round((float) $reviews->average, 2) : null, 'total_reviews' => (int) $reviews->total],
            'trends' => ['appointments' => $trend],
            'revenue' => ['available' => false, 'reason' => 'Consultation payments are not tracked. Platform subscription payments are expenses, not consultation revenue.'],
        ];
    }
}
