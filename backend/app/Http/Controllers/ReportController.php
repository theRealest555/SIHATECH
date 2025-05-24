<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\UserSubscription;
use App\Models\User;
use App\Models\Rendezvous;
use App\Exports\FinancialReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function financialStats(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $stats = [
            'total_revenue' => Payment::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'subscription_revenue' => Payment::whereHas('userSubscription')
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'active_subscriptions' => UserSubscription::where('status', 'active')->count(),
            'new_subscriptions' => UserSubscription::whereBetween('created_at', [$startDate, $endDate])->count(),
            'cancelled_subscriptions' => UserSubscription::where('status', 'cancelled')
                ->whereBetween('cancelled_at', [$startDate, $endDate])
                ->count(),
            'payment_methods' => Payment::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('payment_method')
                ->selectRaw('payment_method, count(*) as count, sum(amount) as total')
                ->get()
        ];

        return response()->json(['data' => $stats]);
    }

    public function rendezvousStats(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $stats = [
            'total_rendezvouss' => Rendezvous::whereBetween('created_at', [$startDate, $endDate])->count(),
            'confirmed_rendezvouss' => Rendezvous::where('statut', 'confirmé')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'cancelled_rendezvouss' => Rendezvous::where('statut', 'annulé')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'completion_rate' => $this->calculateCompletionRate($startDate, $endDate),
            'popular_specialties' => $this->getPopularSpecialties($startDate, $endDate),
            'peak_hours' => $this->getPeakHours($startDate, $endDate)
        ];

        return response()->json(['data' => $stats]);
    }

    public function exportFinancialReport(Request $request)
    {
        $format = $request->get('format', 'csv');
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $data = $this->getFinancialReportData($startDate, $endDate);

        if ($format === 'csv') {
            $export = new FinancialReportExport($data);

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="rapport_financier_' . now()->format('Y-m-d') . '.csv"'
            ];

            $callback = function() use ($export) {
                $file = fopen('php://output', 'w');

                // Add headers
                fputcsv($file, $export->headings());

                // Add data
                foreach ($export->collection() as $row) {
                    fputcsv($file, $row->toArray());
                }

                fclose($file);
            };

            return Response::stream($callback, 200, $headers);
        }

        // For PDF format, return JSON response
        return response()->json([
            'message' => 'PDF export functionality requires additional setup',
            'data' => $data
        ]);
    }

    private function calculateCompletionRate($startDate, $endDate): float
    {
        $total = Rendezvous::whereBetween('created_at', [$startDate, $endDate])->count();
        $completed = Rendezvous::where('statut', 'terminé')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    private function getPopularSpecialties($startDate, $endDate)
    {
        return Rendezvous::join('doctors', 'rendezvous.doctor_id', '=', 'doctors.id')
            ->join('specialities', 'doctors.speciality_id', '=', 'specialities.id')
            ->whereBetween('rendezvous.created_at', [$startDate, $endDate])
            ->groupBy('specialities.nom')
            ->selectRaw('specialities.nom as specialty, count(*) as count')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    private function getPeakHours($startDate, $endDate)
    {
        return Rendezvous::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('HOUR(date_heure) as hour, count(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    private function getFinancialReportData($startDate, $endDate)
    {
        return [
            'payments' => Payment::with(['user', 'userSubscription.subscriptionPlan'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get(),
            'subscriptions' => UserSubscription::with(['user', 'subscriptionPlan'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get(),
            'summary' => [
                'total_revenue' => Payment::where('status', 'completed')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount'),
                'total_transactions' => Payment::whereBetween('created_at', [$startDate, $endDate])->count()
            ]
        ];
    }
}
