<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Rendezvous;
use App\Models\Leave;
use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    /**
     * Map English day names to French for horaires keys
     */
    private function mapDayToFrench(string $englishDay): string
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

    /**
     * Get available appointment slots for a specific doctor and date
     */
    public function getAvailableSlots(Request $request, Doctor $doctor): JsonResponse
    {
        $date = Carbon::parse($request->query('date', Carbon::today()->toDateString()));
        $day = $this->mapDayToFrench($date->format('l'));

        // Check if doctor is on leave
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

        // Get doctor's schedule for the day
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

        // Get existing appointments (fixed: patient_id references users table)
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

    /**
     * Book a new appointment
     */
    public function bookAppointment(BookAppointmentRequest $request, int $doctorId): JsonResponse
    {
        Log::info('bookAppointment started', ['doctorId' => $doctorId, 'request' => $request->all()]);

        try {
            return DB::transaction(function () use ($request, $doctorId) {
                $user = auth()->guard('sanctum')->user();
                Log::info('Authenticated user', ['user' => $user ? json_decode(json_encode($user), true) : null]);

                // Allow patients to book appointments
                if (!$user || $user->role !== 'patient') {
                    return response()->json(['message' => 'Only patients can book appointments'], 403);
                }

                $validated = $request->validated();
                Log::info('Validation passed', ['validated' => $validated]);

                $dateHeure = Carbon::parse($validated['date_heure']);
                $doctor = Doctor::findOrFail($doctorId);

                // Check if the slot is still available
                $existingAppointment = Rendezvous::where('doctor_id', $doctorId)
                    ->where('date_heure', $dateHeure)
                    ->whereNotIn('statut', ['annulé', 'terminé'])
                    ->first();

                if ($existingAppointment) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This time slot is no longer available'
                    ], 409);
                }

                // Create appointment (patient_id references users table directly)
                $rendezvous = Rendezvous::create([
                    'patient_id' => $user->id, // Use authenticated user's ID directly
                    'doctor_id' => $doctorId,
                    'date_heure' => $dateHeure,
                    'statut' => 'en_attente',
                ]);

                Log::info('Appointment booked successfully', [
                    'rendezvous_id' => $rendezvous->id,
                    'doctor_id' => $doctorId,
                    'date_heure' => $dateHeure->toDateTimeString(),
                ]);

                return response()->json([
                    'status' => 'success',
                    'data' => $rendezvous->load(['doctor.user', 'doctor.speciality']),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('bookAppointment failed', [
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

    /**
     * Update an appointment's status
     */
    public function updateAppointmentStatus(UpdateAppointmentStatusRequest $request, Rendezvous $rendezvous): JsonResponse
    {
        $user = auth::user();

        // Check if user is authorized to update this appointment
        if ($user->role === 'patient' && $rendezvous->patient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'medecin' && $rendezvous->doctor_id !== $user->doctor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rendezvous->update([
            'statut' => $request->validated()['statut'],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $rendezvous->load(['doctor.user', 'doctor.speciality', 'patient']),
        ]);
    }

    /**
     * Update an appointment's status by ID
     */
    public function updateStatus(UpdateAppointmentStatusRequest $request, int $id): JsonResponse
    {
        $appointment = Rendezvous::findOrFail($id);
        $user = auth::user();

        // Check if user is authorized to update this appointment
        if ($user->role === 'patient' && $appointment->patient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'medecin' && $appointment->doctor_id !== $user->doctor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $appointment->statut = $request->validated()['statut'];
        $appointment->save();

        return response()->json([
            'status' => 'success',
            'data' => $appointment->load(['doctor.user', 'doctor.speciality', 'patient']),
        ]);
    }

    /**
     * Get appointments with filtering options
     */
    public function getAppointments(Request $request): JsonResponse
    {
        $user = auth::user();
        $doctorId = $request->query('doctor_id');
        $patientId = $request->query('patient_id');
        $date = $request->has('date') ? Carbon::parse($request->query('date')) : null;

        $query = Rendezvous::query()
            ->with(['doctor.user', 'doctor.speciality', 'patient'])
            ->orderBy('date_heure', 'asc');

        // Apply role-based filtering
        if ($user->role === 'patient') {
            $query->where('patient_id', $user->id);
        } elseif ($user->role === 'medecin') {
            $query->where('doctor_id', $user->doctor->id);
        } elseif ($user->role === 'admin') {
            // Admin can see all appointments with optional filters
            if ($doctorId) {
                $query->where('doctor_id', $doctorId);
            }
            if ($patientId) {
                $query->where('patient_id', $patientId);
            }
        }

        if ($date) {
            $query->whereDate('date_heure', $date);
        }

        $appointments = $query->get()->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'patient_name' => $appointment->patient
                    ? ($appointment->patient->prenom . ' ' . $appointment->patient->nom)
                    : 'N/A',
                'date_heure' => $appointment->date_heure->format('Y-m-d H:i:s'),
                'statut' => $appointment->statut,
                'doctor_name' => $appointment->doctor && $appointment->doctor->user
                    ? ($appointment->doctor->user->prenom . ' ' . $appointment->doctor->user->nom)
                    : 'N/A',
                'speciality' => $appointment->doctor && $appointment->doctor->speciality
                    ? $appointment->doctor->speciality->nom
                    : 'N/A',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $appointments,
        ]);
    }
}
