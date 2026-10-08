<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Services\DoctorStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StatisticsController extends Controller
{
    private function statistics(Request $request): array
    {
        $data = $request->validate([
            'period' => 'nullable|in:week,month,year',
            'start_date' => 'nullable|required_with:end_date|date_format:Y-m-d',
            'end_date' => 'nullable|required_with:start_date|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        $now = CarbonImmutable::now();
        $period = $data['period'] ?? 'month';
        $start = match ($period) {
            'week' => $now->startOfWeek(), 'year' => $now->startOfYear(), default => $now->startOfMonth()
        };
        $end = match ($period) {
            'week' => $now->endOfWeek(), 'year' => $now->endOfYear(), default => $now->endOfMonth()
        };
        if (! empty($data['start_date'])) {
            $start = CarbonImmutable::parse($data['start_date']);
            $end = CarbonImmutable::parse($data['end_date']);
        }
        $start = $start->startOfDay();
        $end = $end->startOfDay();
        if ($start->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['end_date' => 'Select a maximum of 366 days.']);
        }
        $doctor = $request->user()->doctor;
        abort_unless($doctor, 404, 'Doctor profile not found.');

        return (new DoctorStatisticsService($doctor))->getDashboardStats($start, $end);
    }

    public function index(Request $request)
    {
        return response()->json(['status' => 'success', 'data' => $this->statistics($request)]);
    }

    public function appointments(Request $request)
    {
        return response()->json(['status' => 'success', 'data' => $this->statistics($request)['appointments']]);
    }

    public function patients(Request $request)
    {
        return response()->json(['status' => 'success', 'data' => $this->statistics($request)['patients']]);
    }

    public function revenue(Request $request)
    {
        $request->validate(['year' => 'nullable|integer|min:1900|max:9999']);

        return response()->json(['status' => 'success', 'data' => $this->statistics($request)['revenue']]);
    }

    public function export(Request $request)
    {
        $request->validate(['type' => 'required|in:appointments,patients,revenue,overview', 'format' => 'nullable|in:csv,pdf']);
        $stats = $this->statistics($request);
        if ($request->input('format', 'csv') !== 'csv' || $request->type === 'revenue') {
            return response()->json(['message' => 'This export is not available. Consultation revenue is not tracked; CSV appointment and patient summaries are supported.'], 422);
        }
        $rows = [['Metric', 'Value'], ['Start date', $stats['range']['start_date']], ['End date (inclusive)', $stats['range']['end_date']], ['Timezone', $stats['range']['timezone']]];
        if ($request->type !== 'patients') {
            $rows[] = ['Total appointments', $stats['appointments']['total']];
            foreach ($stats['appointments']['by_status'] as $status => $count) {
                $rows[] = [$status, $count];
            }
        }
        if ($request->type !== 'appointments') {
            $rows[] = ['Unique booked patients', $stats['patients']['total_unique']];
            $rows[] = ['Patients with completed consultations', $stats['patients']['seen']];
            $rows[] = ['Patients with multiple completed consultations in range', $stats['patients']['repeat_completed']];
        }

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($file, $row, ',', '"', '');
            }
            fclose($file);
        }, 'doctor-statistics-'.$stats['range']['start_date'].'-'.$stats['range']['end_date'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
