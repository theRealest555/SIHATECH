<?php

namespace App\Jobs;

use App\Services\AttendanceAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

// Retained for legacy queue payloads. Only a human decision records attendance.
class MarkNoShowAppointments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $counts = app(AttendanceAudit::class)->counts();
        if (array_sum($counts) > 0) {
            Log::warning('Appointment attendance requires human review', $counts);
        } else {
            Log::info('Appointment attendance audit found no overdue decisions', $counts);
        }
    }
}
