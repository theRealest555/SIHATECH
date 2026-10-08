<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Leave;
use App\Models\Rendezvous;
use Illuminate\Support\Carbon;

class AppointmentAvailability
{
    public const DURATION_MINUTES = 30;

    public function slots(Doctor $doctor, Carbon $date): array
    {
        if (! $doctor->is_verified || ! $doctor->is_active || $doctor->user?->status !== 'actif' || ! $doctor->user?->hasVerifiedEmail()) {
            return [];
        }
        if (Leave::where('doctor_id', $doctor->id)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
            return [];
        }
        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $slots = [];
        foreach (($doctor->horaires ?? [])[$days[$date->dayOfWeek]] ?? [] as $range) {
            if (! is_string($range) || ! preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $range, $parts)) {
                continue;
            }
            $start = Carbon::parse($date->toDateString().' '.$parts[1]);
            $end = Carbon::parse($date->toDateString().' '.$parts[2]);
            while ($start->copy()->addMinutes(self::DURATION_MINUTES)->lte($end)) {
                if ($start->isFuture()) {
                    $slots[] = $start->format('H:i');
                }
                $start->addMinutes(self::DURATION_MINUTES);
            }
        }
        $booked = Rendezvous::where('doctor_id', $doctor->id)->whereDate('date_heure', $date)
            ->where('statut', '!=', 'annulé')->pluck('date_heure');

        return array_values(array_unique(array_filter($slots, function ($time) use ($date, $booked) {
            $start = Carbon::parse($date->toDateString().' '.$time);
            $end = $start->copy()->addMinutes(self::DURATION_MINUTES);

            return ! $booked->contains(fn ($existing) => $existing->lt($end) && $existing->copy()->addMinutes(self::DURATION_MINUTES)->gt($start));
        })));
    }
}
