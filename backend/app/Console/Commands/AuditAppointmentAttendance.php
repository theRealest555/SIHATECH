<?php

namespace App\Console\Commands;

use App\Services\AttendanceAudit;
use Illuminate\Console\Command;

class AuditAppointmentAttendance extends Command
{
    protected $signature = 'appointments:audit-attendance {--json : Output aggregate counts as JSON}';

    protected $description = 'Read-only check for past appointments that need a human attendance decision';

    public function handle(AttendanceAudit $audit): int
    {
        $counts = $audit->counts();
        if ($this->option('json')) {
            $this->line(json_encode($counts, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Count'], collect($counts)->map(fn ($count, $check) => [$check, $count])->values()->all());
            $this->info('Elapsed time does not establish attendance. No statuses were changed.');
        }

        return array_sum($counts) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
