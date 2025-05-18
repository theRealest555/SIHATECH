<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Rendezvous;
use App\Http\Requests\SearchDoctorsRequest;
use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Leave;

class AppointmentController extends Controller
{
    // Map English day names to French for horaires keys
    private function mapDayToFrench($englishDay)
    {
        $dayMap = [
            'monday' => 'lundi',
            'tuesday' => 'mardi',
            'wednesday' => 'mercredi',
            'thursday' => 'jeudi',
            'friday' => 'vendredi',
            'saturday' => 'samedi',
            'sunday' => 'dimanche',
        ];
        return $dayMap[strtolower($englishDay)] ?? strtolower($englishDay);
    }

    public function getAvailableSlots(Request $request, Doctor $doctor)
    {
        $date = Carbon::parse($request->query('date', Carbon::today()->toDateString()));
        $day = $this->mapDayToFrench($date->format('l')); // Map to French day name

        $isOnLeave = Leave::where('doctor_id', $doctor->id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();

        if ($isOnLeave) {
            Log::info('No slots available due to leave', [
                'doctor_id' => $doctor->id,
                'date' => $date->toDateString()
            ]);
            return response()->json([
                'status' => 'success',
                'data' => [],
                'meta' => [
                    'doctor_id' => $doctor->id,
                    'date' => $date->toDateString(),
                    'day_of_week' => $day,
                    'total_slots' => 0,
                    'booked_slots' => 0,
                    'available_slots' => 0
                ]
            ]);
        }

        $horaires = $doctor->horaires ?? [];
        $dailyHorairesRaw = $horaires[$day] ?? [];
        $dailyHoraires = [];

        if (is_string($dailyHorairesRaw)) {
            $dailyHoraires = array_map('trim', explode(',', $dailyHorairesRaw));
        } elseif (is_array($dailyHorairesRaw)) {
            $dailyHoraires = $dailyHorairesRaw;
        }

        Log::info('getAvailableSlots debug', [
            'doctor_id' => $doctor->id,
            'date' => $date->toDateString(),
            'day' => $day,
            'horaires' => $horaires,
            'daily_horaires_raw' => $dailyHorairesRaw,
            'daily_horaires' => $dailyHoraires,
        ]);

        $slots = [];
        $slotDuration = 30;

        foreach ($dailyHoraires as $timeRange) {
            if (is_string($timeRange) && strpos($timeRange, '-') !== false) {
                [$start, $end] = explode('-', $timeRange);
                $start = trim($start);
                $end = trim($end);

                if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start) &&
                    preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end)) {
                    $startTime = Carbon::parse($date->toDateString() . ' ' . $start);
                    $endTime = Carbon::parse($date->toDateString() . ' ' . $end);

                    while ($startTime < $endTime) {
                        $slots[] = $startTime->format('H:i');
                        $startTime->addMinutes($slotDuration);
                    }
                } else {
                    Log::warning('Invalid time range format', ['timeRange' => $timeRange]);
                }
            }
        }

        $existingAppointments = Rendezvous::where('doctor_id', $doctor->id)
            ->whereDate('date_heure', $date)
            ->whereNotIn('statut', ['annulé', 'terminé'])
            ->pluck('date_heure')
            ->map(fn($dt) => $dt->format('H:i'))
            ->toArray();

        Log::info('Existing appointments', ['appointments' => $existingAppointments]);

        $availableSlots = array_diff($slots, $existingAppointments);

        return response()->json([
            'status' => 'success',
            'data' => array_values($availableSlots),
            'meta' => [
                'doctor_id' => $doctor->id,
                'date' => $date->toDateString(),
                'day_of_week' => $day,
                'total_slots' => count($slots),
                'booked_slots' => count($existingAppointments),
                'available_slots' => count($availableSlots)
            ]
        ]);
    }

    private function getAvailableSlotsForDoctor(Doctor $doctor, Carbon $date)
    {
        $day = $this->mapDayToFrench($date->format('l')); // Map to French day name
        $horaires = $doctor->horaires ?? [];
        $dailyHorairesRaw = $horaires[$day] ?? [];
        $dailyHoraires = [];

        if (is_string($dailyHorairesRaw)) {
            $dailyHoraires = array_map('trim', explode(',', $dailyHorairesRaw));
        } elseif (is_array($dailyHorairesRaw)) {
            $dailyHoraires = $dailyHorairesRaw;
        }

        $slots = [];
        $slotDuration = 30;

        foreach ($dailyHoraires as $timeRange) {
            if (is_string($timeRange) && strpos($timeRange, '-') !== false) {
                [$start, $end] = explode('-', $timeRange);
                $start = trim($start);
                $end = trim($end);

                if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start) &&
                    preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end)) {
                    $startTime = Carbon::parse($date->toDateString() . ' ' . $start);
                    $endTime = Carbon::parse($date->toDateString() . ' ' . $end);

                    while ($startTime < $endTime) {
                        $slots[] = $startTime->format('H:i');
                        $startTime->addMinutes($slotDuration);
                    }
                }
            }
        }

        $existingAppointments = Rendezvous::where('doctor_id', $doctor->id)
            ->whereDate('date_heure', $date)
            ->whereNotIn('statut', ['annulé', 'terminé'])
            ->pluck('date_heure')
            ->map(fn($dt) => $dt->format('H:i'))
            ->toArray();

        return array_diff($slots, $existingAppointments);
    }

    public function bookAppointment(Request $request, $doctorId)
{
    \Log::info('bookAppointment started', ['doctorId' => $doctorId, 'request' => $request->all()]);
    try {
        $response = DB::transaction(function () use ($request, $doctorId) {
            $user = auth()->guard('sanctum')->user();
            \Log::info('Authenticated user', ['user' => $user ? $user->toArray() : null]);

            if ($user->role !== 'medecin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $validated = $request->validate([
                'patient_id' => 'required|exists:users,id,role,patient',
                'date_heure' => 'required|date|after_or_equal:' . now(),
            ]);
            \Log::info('Validation passed', ['validated' => $validated]);

            $dateHeure = Carbon::parse($validated['date_heure']);
            $doctor = Doctor::findOrFail($doctorId);
            \Log::info('Booking attempt', [
                'doctor_id' => $doctorId,
                'date_heure' => $dateHeure->toDateTimeString(),
            ]);

            $isBooked = Rendezvous::where('doctor_id', $doctorId)
                ->where('date_heure', $dateHeure)
                ->whereNotIn('statut', ['annulé', 'terminé'])
                ->exists();

            if ($isBooked) {
                \Log::warning('Slot already booked', [
                    'doctor_id' => $doctorId,
                    'date_heure' => $dateHeure->toDateTimeString(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ce créneau est déjà réservé.',
                ], 409);
            }

            $day = strtolower($dateHeure->format('l'));
            $schedule = $doctor->availabilities()->where('day_of_week', $day)->first();
            $dailyHoraires = $schedule ? [$schedule->time_range] : [];
            $slotTime = $dateHeure->format('H:i');
            $isValidSlot = false;

            \Log::info('Availability check', [
                'day' => $day,
                'schedule' => $schedule ? $schedule->toArray() : null,
                'dailyHoraires' => $dailyHoraires,
            ]);

            foreach ($dailyHoraires as $timeRange) {
                if (is_string($timeRange) && strpos($timeRange, '-') !== false) {
                    [$start, $end] = explode('-', $timeRange);
                    $startTime = Carbon::parse($dateHeure->toDateString() . ' ' . $start);
                    $endTime = Carbon::parse($dateHeure->toDateString() . ' ' . $end);
                    if (Carbon::parse($dateHeure->toDateString() . ' ' . $slotTime)->between($startTime, $endTime, true)) {
                        $isValidSlot = true;
                    }
                }
            }

            $existingAppointments = Rendezvous::where('doctor_id', $doctorId)
                ->whereDate('date_heure', $dateHeure)
                ->whereNotIn('statut', ['annulé', 'terminé'])
                ->select('date_heure')
                ->get()
                ->pluck('date_heure')
                ->map(fn($dt) => $dt->format('H:i'))
                ->toArray();

            \Log::info('Existing appointments check', [
                'doctor_id' => $doctorId,
                'existingAppointments' => $existingAppointments,
            ]);

            $availableSlots = array_diff([$slotTime], $existingAppointments);
            if (!$isValidSlot || empty($availableSlots)) {
                \Log::warning('Invalid slot time', [
                    'doctor_id' => $doctorId,
                    'date_heure' => $dateHeure->toDateTimeString(),
                    'slot_time' => $slotTime,
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ce créneau n\'est pas disponible dans les horaires du médecin.',
                ], 400);
            }

            $rendezvous = Rendezvous::create([
                'patient_id' => $validated['patient_id'],
                'doctor_id' => $doctorId,
                'date_heure' => $dateHeure,
                'statut' => 'en_attente',
            ]);

            \Log::info('Appointment booked successfully', [
                'rendezvous_id' => $rendezvous->id,
                'doctor_id' => $doctorId,
                'date_heure' => $dateHeure->toDateTimeString(),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $rendezvous,
            ], 201);
        });
        \Log::info('bookAppointment response sent', ['response' => $response->getContent()]);
        return $response;
    } catch (\Exception $e) {
        \Log::error('bookAppointment failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to book appointment',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function updateAppointmentStatus(UpdateAppointmentStatusRequest $request, Rendezvous $rendezvous)
    {
        $rendezvous->update([
            'statut' => $request->statut,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $rendezvous,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $appointment = Rendezvous::findOrFail($id);
        $request->validate([
            'statut' => 'required|in:confirmé,en_attente,annulé,terminé',
        ]);

        $appointment->statut = $request->input('statut');
        $appointment->save();

        return response()->json([
            'status' => 'success',
            'data' => $appointment,
        ]);
    }

    public function getAppointments(Request $request)
    {
        $doctorId = $request->query('doctor_id');
        $patientId = $request->query('patient_id');
        $date = $request->has('date') ? Carbon::parse($request->query('date')) : null;

        $query = Rendezvous::query()
            ->with(['doctor.user', 'doctor.speciality', 'patient.user'])
            ->orderBy('date_heure', 'asc');

        if ($doctorId) {
            $query->where('doctor_id', $doctorId);
        }

        if ($patientId) {
            $query->where('patient_id', $patientId);
        }

        if ($date) {
            $query->whereDate('date_heure', $date);
        }

        $appointments = $query->get()->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'patient_name' => $appointment->patient && $appointment->patient->user ? ($appointment->patient->user->prenom . ' ' . $appointment->patient->user->nom) : 'N/A',
                'date_heure' => $appointment->date_heure->format('Y-m-d H:i:s'),
                'statut' => $appointment->statut,
                'doctor_name' => $appointment->doctor && $appointment->doctor->user ? ($appointment->doctor->user->prenom . ' ' . $appointment->doctor->user->nom) : 'N/A',
                'speciality' => $appointment->doctor && $appointment->doctor->speciality ? $appointment->doctor->speciality->nom : 'N/A',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $appointments,
        ]);
    }
}