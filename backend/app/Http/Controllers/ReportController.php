<?php
// app/Http/Controllers/ReportController.php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\UserSubscription;
use App\Models\User;
use App\Models\rendezvous;
use App\Exports\FinancialReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'total_rendezvouss' => rendezvous::whereBetween('created_at', [$startDate, $endDate])->count(),
            'confirmed_rendezvouss' => rendezvous::where('status', 'confirmed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'cancelled_rendezvouss' => rendezvous::where('status', 'cancelled')
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
        $format = $request->get('format', 'excel');
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $data = $this->getFinancialReportData($startDate, $endDate);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.financial', compact('data', 'startDate', 'endDate'));
            return $pdf->download('rapport_financier_' . now()->format('Y-m-d') . '.pdf');
        }

        return Excel::download(
            new FinancialReportExport($data),
            'rapport_financier_' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    private function calculateCompletionRate($startDate, $endDate): float
    {
        $total = rendezvous::whereBetween('created_at', [$startDate, $endDate])->count();
        $completed = rendezvous::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    private function getPopularSpecialties($startDate, $endDate)
    {
        return rendezvous::join('users', 'rendezvouss.doctor_id', '=', 'users.id')
            ->whereBetween('rendezvouss.created_at', [$startDate, $endDate])
            ->groupBy('users.specialty')
            ->selectRaw('users.specialty, count(*) as count')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    private function getPeakHours($startDate, $endDate)
    {
        return rendezvous::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('HOUR(rendezvous_date) as hour, count(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    private function getFinancialReportData($startDate, $endDate)
    {
        return [
            'payments' => Payment::with(['user', 'userSubscription.Abonnement'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get(),
            'subscriptions' => UserSubscription::with(['user', 'Abonnement'])
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