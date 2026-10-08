<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WeeklySchedule implements ValidationRule
{
    public const DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $schedule = is_string($value) ? json_decode($value, true) : $value;
        if (! is_array($schedule)) {
            $fail('The schedule must contain days and time ranges.');

            return;
        }
        foreach ($schedule as $day => $ranges) {
            if (! in_array($day, self::DAYS, true) || ! is_array($ranges) || ! array_is_list($ranges)) {
                $fail('Use French day names with a list of HH:mm-HH:mm ranges.');

                return;
            }
            $previous = [];
            foreach ($ranges as $range) {
                if (! is_string($range) || ! preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]-([01][0-9]|2[0-3]):[0-5][0-9]$/', $range)) {
                    $fail('Time ranges must use HH:mm-HH:mm.');

                    return;
                }
                [$start, $end] = explode('-', $range);
                $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
                if ($start >= $end || $minutes($end) - $minutes($start) < 30) {
                    $fail('Each range must include at least one 30-minute appointment and end after it starts.');

                    return;
                }
                foreach ($previous as [$otherStart, $otherEnd]) {
                    if ($start < $otherEnd && $end > $otherStart) {
                        $fail('Time ranges on the same day cannot overlap.');

                        return;
                    }
                }
                $previous[] = [$start, $end];
            }
        }
    }
}
