<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\CheckSubscriptionRenewals;
use App\Jobs\MarkNoShowAppointments;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check for subscription renewals daily at 9 AM
        $schedule->job(new CheckSubscriptionRenewals())
            ->dailyAt('09:00')
            ->name('check-subscription-renewals')
            ->withoutOverlapping()
            ->onOneServer();

        // Check for no-show appointments every hour
        $schedule->job(new MarkNoShowAppointments())
            ->hourly()
            ->name('mark-no-show-appointments')
            ->withoutOverlapping()
            ->onOneServer();

        // Clean up old notifications (older than 90 days)
        $schedule->command('notifications:clean')
            ->weekly()
            ->sundays()
            ->at('02:00');

        // Generate daily statistics report
        $schedule->command('statistics:generate-daily')
            ->dailyAt('23:59')
            ->name('generate-daily-statistics')
            ->withoutOverlapping();

        // Backup database daily
        $schedule->command('backup:run')
            ->daily()
            ->at('03:00')
            ->name('database-backup')
            ->withoutOverlapping();

        // Clean up expired sessions
        $schedule->command('session:gc')
            ->daily()
            ->at('04:00');

        // Send appointment reminders (24 hours before)
        $schedule->command('appointments:send-reminders')
            ->everyFiveMinutes()
            ->name('send-appointment-reminders')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
