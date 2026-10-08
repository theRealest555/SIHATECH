<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Rendezvous;
use App\Rules\WeeklySchedule;
use Illuminate\Http\Exceptions\HttpResponseException;

class DoctorSchedule
{
    // Call while holding the doctor's row lock, shared with appointment booking.
    public function assertBookingsFit(Doctor $doctor, array $schedule): void
    {
        $bookings = Rendezvous::where('doctor_id', $doctor->id)
            ->whereIn('statut', ['en_attente', 'confirmé'])
            ->where('date_heure', '>=', now())->get();
        foreach ($bookings as $booking) {
            $start = $booking->date_heure;
            $fits = false;
            foreach ($schedule[WeeklySchedule::DAYS[$start->dayOfWeek]] ?? [] as $range) {
                [$from, $to] = explode('-', $range);
                $rangeStart = $start->copy()->setTimeFromTimeString($from);
                $rangeEnd = $start->copy()->setTimeFromTimeString($to);
                $offset = $rangeStart->diffInSeconds($start, false);
                if ($offset >= 0 && fmod($offset, AppointmentAvailability::DURATION_MINUTES * 60) === 0.0
                    && $start->copy()->addMinutes(AppointmentAvailability::DURATION_MINUTES)->lte($rangeEnd)) {
                    $fits = true;
                    break;
                }
            }
            if (! $fits) {
                throw new HttpResponseException(response()->json([
                    'status' => 'error',
                    'message' => 'Le nouvel horaire entre en conflit avec des rendez-vous existants.',
                ], 409));
            }
        }
    }
}
