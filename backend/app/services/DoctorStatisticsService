<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Rendezvous;
use App\Models\Payment;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DoctorStatisticsService
{
    protected $doctor;

    public function __construct(Doctor $doctor)
    {
        $this->doctor = $doctor;
    }

    /**
     * Get comprehensive statistics for the doctor's dashboard
     */
    public function getDashboardStats(): array
    {
        return [
            'overview' => $this->getOverviewStats(),
            'appointments' => $this->getAppointmentStats(),
            'patients' => $this->getPatientStats(),
            'revenue' => $this->getRevenueStats(),
            'performance' => $this->getPerformanceStats(),
            'trends' => $this->getTrendsData(),
        ];
    }

    /**
     * Get overview statistics
     */
    protected function getOverviewStats(): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        return [
            'total_patients' => $this->getTotalUniquePatients(),
            'appointments_today' => $this->doctor->appointments()
                ->whereDate('date_heure', $today)
                ->count(),
            'appointments_this_week' => $this->doctor->appointments()
                ->whereBetween('date_heure', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->count(),
            'appointments_this_month' => $this->doctor->appointments()
                ->whereBetween('date_heure', [$thisMonth, Carbon::now()])
                ->count(),
            'rating' => [
                'average' => $this->doctor->average_rating,
                'total_reviews' => $this->doctor->total_reviews,
            ],
            'subscription_status' => $this->getSubscriptionStatus(),
        ];
    }

    /**
     * Get detailed appointment statistics
     */
    protected function getAppointmentStats(): array
    {
        $appointments = $this->doctor->appointments()
            ->selectRaw('statut, COUNT(*) as count')
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        $totalAppointments = array_sum($appointments);

        return [
            'total' => $totalAppointments,
            'by_status' => [
                'confirmed' => $appointments['confirmé'] ?? 0,
                'pending' => $appointments['en_attente'] ?? 0,
                'completed' => $appointments['terminé'] ?? 0,
                'cancelled' => $appointments['annulé'] ?? 0,
                'no_show' => $appointments['no_show'] ?? 0,
            ],
            'rates' => [
                'completion_rate' => $totalAppointments > 0
                    ? round((($appointments['terminé'] ?? 0) / $totalAppointments) * 100, 2)
                    : 0,
                'no_show_rate' => $totalAppointments > 0
                    ? round((($appointments['no_show'] ?? 0) / $totalAppointments) * 100, 2)
                    : 0,
                'cancellation_rate' => $totalAppointments > 0
                    ? round((($appointments['annulé'] ?? 0) / $totalAppointments) * 100, 2)
                    : 0,
            ],
            'average_per_day' => $this->getAverageAppointmentsPerDay(),
            'peak_hours' => $this->getPeakAppointmentHours(),
        ];
    }

    /**
     * Get patient statistics
     */
    protected function getPatientStats(): array
    {
        $newPatientsThisMonth = $this->doctor->appointments()
            ->selectRaw('patient_id, MIN(created_at) as first_appointment')
            ->groupBy('patient_id')
            ->havingRaw('MIN(created_at) >= ?', [Carbon::now()->startOfMonth()])
            ->count();

        $returningPatients = $this->doctor->appointments()
            ->selectRaw('patient_id, COUNT(*) as appointment_count')
            ->groupBy('patient_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $totalUniquePatients = $this->getTotalUniquePatients();

        return [
            'total_unique' => $totalUniquePatients,
            'new_this_month' => $newPatientsThisMonth,
            'returning' => $returningPatients,
            'retention_rate' => $totalUniquePatients > 0
                ? round(($returningPatients / $totalUniquePatients) * 100, 2)
                : 0,
            'demographics' => $this->getPatientDemographics(),
        ];
    }

    /**
     * Get revenue statistics
     */
    protected function getRevenueStats(): array
    {
        $currentYear = Carbon::now()->year;
        $lastYear = Carbon::now()->subYear()->year;

        // Get subscription payments for this doctor's user
        $currentYearRevenue = Payment::where('user_id', $this->doctor->user_id)
            ->where('status', 'completed')
            ->whereYear('created_at', $currentYear)
            ->sum('amount');

        $lastYearRevenue = Payment::where('user_id', $this->doctor->user_id)
            ->where('status', 'completed')
            ->whereYear('created_at', $lastYear)
            ->sum('amount');

        $monthlyRevenue = Payment::where('user_id', $this->doctor->user_id)
            ->where('status', 'completed')
            ->whereYear('created_at', $currentYear)
            ->selectRaw('MONTH(created_at) as month, SUM(amount) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        return [
            'total_this_year' => $currentYearRevenue,
            'total_last_year' => $lastYearRevenue,
            'growth_percentage' => $lastYearRevenue > 0
                ? round((($currentYearRevenue - $lastYearRevenue) / $lastYearRevenue) * 100, 2)
                : 0,
            'monthly_breakdown' => $this->formatMonthlyRevenue($monthlyRevenue),
            'average_monthly' => count($monthlyRevenue) > 0
                ? round(array_sum($monthlyRevenue) / count($monthlyRevenue), 2)
                : 0,
        ];
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceStats(): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Average consultation duration (assuming 30 minutes per appointment)
        $completedAppointments = $this->doctor->appointments()
            ->where('statut', 'terminé')
            ->where('date_heure', '>=', $thirtyDaysAgo)
            ->count();

        // Patient satisfaction from reviews
        $recentReviews = $this->doctor->approvedReviews()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as count')
            ->first();

        return [
            'consultations_last_30_days' => $completedAppointments,
            'patient_satisfaction' => [
                'average_rating' => round($recentReviews->avg_rating ?? 0, 2),
                'total_reviews' => $recentReviews->count ?? 0,
            ],
            'response_time' => $this->getAverageResponseTime(),
            'availability_score' => $this->calculateAvailabilityScore(),
        ];
    }

    /**
     * Get trends data for charts
     */
    protected function getTrendsData(): array
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();

        // Appointments trend
        $appointmentsTrend = $this->doctor->appointments()
            ->where('date_heure', '>=', $sixMonthsAgo)
            ->selectRaw('DATE_FORMAT(date_heure, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'count' => $item->count,
                ];
            });

        // No-show trend
        $noShowTrend = $this->doctor->appointments()
            ->where('date_heure', '>=', $sixMonthsAgo)
            ->where('statut', 'no_show')
            ->selectRaw('DATE_FORMAT(date_heure, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // Patient growth trend
        $patientGrowth = $this->doctor->appointments()
            ->where('date_heure', '>=', $sixMonthsAgo)
            ->selectRaw('DATE_FORMAT(date_heure, "%Y-%m") as month, COUNT(DISTINCT patient_id) as unique_patients')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'patients' => $item->unique_patients,
                ];
            });

        return [
            'appointments' => $appointmentsTrend,
            'no_shows' => $this->formatNoShowTrend($noShowTrend),
            'patient_growth' => $patientGrowth,
        ];
    }

    /**
     * Get total unique patients
     */
    protected function getTotalUniquePatients(): int
    {
        return $this->doctor->appointments()
            ->distinct('patient_id')
            ->count('patient_id');
    }

    /**
     * Get average appointments per day
     */
    protected function getAverageAppointmentsPerDay(): float
    {
        $firstAppointment = $this->doctor->appointments()->min('date_heure');

        if (!$firstAppointment) {
            return 0;
        }

        $daysSinceFirst = Carbon::parse($firstAppointment)->diffInDays(now());
        $totalAppointments = $this->doctor->appointments()->count();

        return $daysSinceFirst > 0 ? round($totalAppointments / $daysSinceFirst, 2) : 0;
    }

    /**
     * Get peak appointment hours
     */
    protected function getPeakAppointmentHours(): array
    {
        return $this->doctor->appointments()
            ->selectRaw('HOUR(date_heure) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => sprintf('%02d:00', $item->hour),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get patient demographics
     */
    protected function getPatientDemographics(): array
    {
        $demographics = DB::table('rendezvous')
            ->join('users', 'rendezvous.patient_id', '=', 'users.id')
            ->where('rendezvous.doctor_id', $this->doctor->id)
            ->selectRaw('
                COUNT(DISTINCT users.id) as total,
                SUM(CASE WHEN users.sexe = "homme" THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN users.sexe = "femme" THEN 1 ELSE 0 END) as female,
                AVG(TIMESTAMPDIFF(YEAR, users.date_de_naissance, CURDATE())) as avg_age
            ')
            ->first();

        return [
            'total' => $demographics->total ?? 0,
            'by_gender' => [
                'male' => $demographics->male ?? 0,
                'female' => $demographics->female ?? 0,
            ],
            'average_age' => round($demographics->avg_age ?? 0),
        ];
    }

    /**
     * Get subscription status
     */
    protected function getSubscriptionStatus(): array
    {
        $activeSubscription = UserSubscription::where('user_id', $this->doctor->user_id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('subscriptionPlan')
            ->first();

        if (!$activeSubscription) {
            return [
                'active' => false,
                'plan' => null,
                'expires_at' => null,
            ];
        }

        return [
            'active' => true,
            'plan' => $activeSubscription->subscriptionPlan->name ?? 'Unknown',
            'expires_at' => $activeSubscription->ends_at->format('Y-m-d'),
            'days_remaining' => $activeSubscription->getRemainingDays(),
        ];
    }

    /**
     * Calculate average response time (placeholder - would need actual response tracking)
     */
    protected function getAverageResponseTime(): string
    {
        // This is a placeholder. In a real implementation, you would track
        // when appointments are requested vs when they are confirmed
        return '< 2 hours';
    }

    /**
     * Calculate availability score based on schedule and appointments
     */
    protected function calculateAvailabilityScore(): int
    {
        // Calculate based on:
        // - Number of available hours per week
        // - Percentage of slots filled
        // - Number of leaves taken

        $totalSlots = 40; // Example: 8 hours/day * 5 days
        $bookedSlots = $this->doctor->appointments()
            ->whereBetween('date_heure', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();

        $utilizationRate = $totalSlots > 0 ? ($bookedSlots / $totalSlots) * 100 : 0;

        // Score based on utilization (not too low, not too high)
        if ($utilizationRate < 30) {
            return 60; // Low utilization
        } elseif ($utilizationRate > 90) {
            return 70; // Over-booked
        } else {
            return min(100, 70 + ($utilizationRate - 30) * 0.5); // Optimal range
        }
    }

    /**
     * Format monthly revenue data
     */
    protected function formatMonthlyRevenue(array $monthlyRevenue): array
    {
        $formatted = [];

        for ($month = 1; $month <= 12; $month++) {
            $formatted[] = [
                'month' => Carbon::create(null, $month)->format('M'),
                'revenue' => $monthlyRevenue[$month] ?? 0,
            ];
        }

        return $formatted;
    }

    /**
     * Format no-show trend data
     */
    protected function formatNoShowTrend(array $noShowData): array
    {
        $formatted = [];

        foreach ($noShowData as $month => $count) {
            $formatted[] = [
                'month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'count' => $count,
            ];
        }

        return $formatted;
    }
}
