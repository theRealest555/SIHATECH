<?php

namespace App\Http\Controllers;

use App\Services\AdminReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    private function report(Request $request, string $type): array
    {
        $data = $request->validate([
            'start_date' => 'nullable|required_with:end_date|date_format:Y-m-d',
            'end_date' => 'nullable|required_with:start_date|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        $start = isset($data['start_date']) ? CarbonImmutable::parse($data['start_date'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $end = isset($data['end_date']) ? CarbonImmutable::parse($data['end_date'])->startOfDay() : CarbonImmutable::now()->endOfMonth()->startOfDay();
        if ($start->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['end_date' => 'Select a maximum of 366 days.']);
        }

        return DB::transaction(fn () => app(AdminReportService::class)->{$type}($start, $end));
    }

    public function financialStats(Request $request)
    {
        return response()->json(['data' => $this->report($request, 'financial')], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function rendezvousStats(Request $request)
    {
        return response()->json(['data' => $this->report($request, 'appointments')], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function exportFinancialReport(Request $request)
    {
        return $this->export($request, 'financial');
    }

    public function exportAppointmentReport(Request $request)
    {
        return $this->export($request, 'appointments');
    }

    private function export(Request $request, string $type)
    {
        $request->validate(['format' => 'nullable|in:csv']);
        $data = $this->report($request, $type);
        $rows = app(AdminReportService::class)->csvRows($type, $data);

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                // Quoting does not prevent spreadsheet formula execution.
                $safe = array_map(fn ($cell) => is_string($cell) && preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $cell) ? "'".$cell : $cell, $row);
                fputcsv($file, $safe, ',', '"', '');
            }
            fclose($file);
        }, 'admin-'.$type.'-'.$data['range']['start_date'].'-'.$data['range']['end_date'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
