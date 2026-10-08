<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ops:heartbeat')->everyMinute()->withoutOverlapping(2);
Schedule::command('documents:cleanup-files')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('appointments:send-reminders')->everyFiveMinutes()->withoutOverlapping(10)
    ->when(fn () => config('operations.appointment_reminders_enabled'));
