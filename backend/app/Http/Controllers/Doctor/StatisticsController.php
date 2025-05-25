<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Services\DoctorStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    /**
     * Get comprehensive statistics for the authenticated doctor
     */
    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found'
            ], 404);
        }

        $statisticsService = new DoctorStatisticsService($doctor);
        $stats = $statisticsService->getDashboardStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Get appointment statistics for a specific period
     */
    public function appointments(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:week,month,year',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $doctor = $request->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found'
            ], 404);
        }

        $period = $request->get('period', 'month');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Get statistics based on period
        $stats = $this->getAppointmentStatsByPeriod($doctor, $period, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Get patient statistics
     */
    public function patients(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found'
            ], 404);
        }

        $statisticsService = new DoctorStatisticsService($doctor);
        $stats = $statisticsService->getDashboardStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats['patients']
        ]);
    }

    /**
     * Get revenue statistics
     */
    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
        ]);

        $doctor = $request->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found'
            ], 404);
        }

        $statisticsService = new DoctorStatisticsService($doctor);
        $stats = $statisticsService->getDashboardStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats['revenue']
        ]);
    }

    /**
     * Export statistics as CSV
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|in:appointments,patients,revenue,overview',
            'format' => 'nullable|in:csv,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $doctor = $request->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found'
            ], 404);
        }

        $type = $request->get('type');
        $format = $request->get('format', 'csv');

        $statisticsService = new DoctorStatisticsService($doctor);
        $stats = $statisticsService->getDashboardStats();

        if ($format === 'csv') {
            return $this->exportAsCSV($stats, $type);
        }

        // PDF export would require additional setup
        return response()->json([
            'status' => 'error',
            'message' => 'PDF export not yet implemented'
        ], 501);
    }

    /**
     * Get appointment statistics by period
     */
    protected function getAppointmentStatsByPeriod($doctor, $period, $startDate = null, $endDate = null): array
    {
        $query = $doctor->appointments();

        if ($startDate && $endDate) {
            $query->whereBetween('date_heure', [$startDate, $endDate]);
        } else {
            switch ($period) {
                case 'week':
                    $query->whereBetween('date_heure', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'year':
                    $query->whereYear('date_heure', now()->year);
                    break;
                case 'month':
                default:
                    $query->whereMonth('date_heure', now()->month)
                          ->whereYear('date_heure', now()->year);
                    break;
            }
        }

        $appointments = $query->selectRaw('
            statut,
            COUNT(*) as count,
            DATE(date_heure) as date
        ')
        ->groupBy('statut', 'date')
        ->get();

        // Group by date for chart data
        $chartData = [];
        foreach ($appointments as $appointment) {
            $date = $appointment->date;
            if (!isset($chartData[$date])) {
                $chartData[$date] = [
                    'date' => $date,
                    'total' => 0,
                    'confirmed' => 0,
                    'completed' => 0,
                    'cancelled' => 0,
                    'no_show' => 0,
                    'pending' => 0,
                ];
            }

            $chartData[$date]['total'] += $appointment->count;

            switch ($appointment->statut) {
                case 'confirmé':
                    $chartData[$date]['confirmed'] = $appointment->count;
                    break;
                case 'terminé':
                    $chartData[$date]['completed'] = $appointment->count;
                    break;
                case 'annulé':
                    $chartData[$date]['cancelled'] = $appointment->count;
                    break;
                case 'no_show':
                    $chartData[$date]['no_show'] = $appointment->count;
                    break;
                case 'en_attente':
                    $chartData[$date]['pending'] = $appointment->count;
                    break;
            }
        }

        return [
            'period' => $period,
            'data' => array_values($chartData),
            'summary' => [
                'total' => collect($chartData)->sum('total'),
                'confirmed' => collect($chartData)->sum('confirmed'),
                'completed' => collect($chartData)->sum('completed'),
                'cancelled' => collect($chartData)->sum('cancelled'),
                'no_show' => collect($chartData)->sum('no_show'),
                'pending' => collect($chartData)->sum('pending'),
            ]
        ];
    }

    /**
     * Export statistics as CSV
     */
    protected function exportAsCSV($stats, $type)
    {
        $filename = "doctor_statistics_{$type}_" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($stats, $type) {
            $file = fopen('php://output', 'w');

            switch ($type) {
                case 'appointments':
                    $this->writeAppointmentsCsv($file, $stats['appointments']);
                    break;
                case 'patients':
                    $this->writePatientsCsv($file, $stats['patients']);
                    break;
                case 'revenue':
                    $this->writeRevenueCsv($file, $stats['revenue']);
                    break;
                case 'overview':
                default:
                    $this->writeOverviewCsv($file, $stats);
                    break;
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Write appointments CSV
     */
    protected function writeAppointmentsCsv($file, $data): void
    {
        fputcsv($file, ['Appointment Statistics']);
        fputcsv($file, ['']);

        fputcsv($file, ['Status', 'Count', 'Percentage']);
        fputcsv($file, ['Confirmed', $data['by_status']['confirmed'], $data['rates']['completion_rate'] . '%']);
        fputcsv($file, ['Completed', $data['by_status']['completed'], '-']);
        fputcsv($file, ['Pending', $data['by_status']['pending'], '-']);
        fputcsv($file, ['Cancelled', $data['by_status']['cancelled'], $data['rates']['cancellation_rate'] . '%']);
        fputcsv($file, ['No Show', $data['by_status']['no_show'], $data['rates']['no_show_rate'] . '%']);

        fputcsv($file, ['']);
        fputcsv($file, ['Total Appointments', $data['total']]);
        fputcsv($file, ['Average Per Day', $data['average_per_day']]);
    }

    /**
     * Write patients CSV
     */
    protected function writePatientsCsv($file, $data): void
    {
        fputcsv($file, ['Patient Statistics']);
        fputcsv($file, ['']);

        fputcsv($file, ['Metric', 'Value']);
        fputcsv($file, ['Total Unique Patients', $data['total_unique']]);
        fputcsv($file, ['New This Month', $data['new_this_month']]);
        fputcsv($file, ['Returning Patients', $data['returning']]);
        fputcsv($file, ['Retention Rate', $data['retention_rate'] . '%']);
    }

    /**
     * Write revenue CSV
     */
    protected function writeRevenueCsv($file, $data): void
    {
        fputcsv($file, ['Revenue Statistics']);
        fputcsv($file, ['']);

        fputcsv($file, ['Metric', 'Value']);
        fputcsv($file, ['Total This Year', $data['total_this_year'] . ' MAD']);
        fputcsv($file, ['Total Last Year', $data['total_last_year'] . ' MAD']);
        fputcsv($file, ['Growth Percentage', $data['growth_percentage'] . '%']);
        fputcsv($file, ['Average Monthly', $data['average_monthly'] . ' MAD']);

        fputcsv($file, ['']);
        fputcsv($file, ['Monthly Breakdown']);
        fputcsv($file, ['Month', 'Revenue (MAD)']);

        foreach ($data['monthly_breakdown'] as $month) {
            fputcsv($file, [$month['month'], $month['revenue']]);
        }
    }

    /**
     * Write overview CSV
     */
    protected function writeOverviewCsv($file, $data): void
    {
        fputcsv($file, ['Doctor Statistics Overview']);
        fputcsv($file, ['Generated on', now()->format('Y-m-d H:i:s')]);
        fputcsv($file, ['']);

        // Overview section
        fputcsv($file, ['Overview']);
        fputcsv($file, ['Total Patients', $data['overview']['total_patients']]);
        fputcsv($file, ['Appointments Today', $data['overview']['appointments_today']]);
        fputcsv($file, ['Appointments This Week', $data['overview']['appointments_this_week']]);
        fputcsv($file, ['Appointments This Month', $data['overview']['appointments_this_month']]);
        fputcsv($file, ['Average Rating', $data['overview']['rating']['average']]);
        fputcsv($file, ['Total Reviews', $data['overview']['rating']['total_reviews']]);

        fputcsv($file, ['']);

        // Performance section
        fputcsv($file, ['Performance Metrics']);
        fputcsv($file, ['Consultations (Last 30 Days)', $data['performance']['consultations_last_30_days']]);
        fputcsv($file, ['Availability Score', $data['performance']['availability_score'] . '%']);
    }
}
