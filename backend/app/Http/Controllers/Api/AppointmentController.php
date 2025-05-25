<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Rendezvous;
use App\Models\Leave;
use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Http\Requests\MarkAppointmentAsNoShowRequest;
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
                    'available_slots' => 0,
                    'is_on_leave' => true
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

        // Get existing appointments (excluding cancelled and no-show)
        $existingAppointments = Rendezvous::where('doctor_id', $doctor->id)
            ->whereDate('date_heure', $date)
            ->whereNotIn('statut', ['annulé', 'terminé', 'no_show'])
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
                'available_slots' => count($availableSlots),
                'is_on_leave' => false
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
                    ->whereNotIn('statut', ['annulé', 'terminé', 'no_show'])
                    ->first();

                if ($existingAppointment) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This time slot is no longer available'
                    ], 409);
                }

                // Create appointment
                $rendezvous = Rendezvous::create([
                    'patient_id' => $user->id,
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

        $validated = $request->validated();

        // Prevent patients from marking appointments as no-show
        if ($user->role === 'patient' && $validated['statut'] === 'no_show') {
            return response()->json([
                'message' => 'Patients cannot mark appointments as no-show'
            ], 403);
        }

        $rendezvous->update([
            'statut' => $validated['statut'],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $rendezvous->load(['doctor.user', 'doctor.speciality', 'patient']),
        ]);
    }

    /**
     * Mark an appointment as no-show
     */
    public function markAsNoShow(MarkAppointmentAsNoShowRequest $request, int $id): JsonResponse
    {
        $appointment = Rendezvous::findOrFail($id);
        $user = auth::user();

        // Additional authorization check
        if ($user->role === 'medecin' && $appointment->doctor_id !== $user->doctor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if appointment is in the past
        if ($appointment->date_heure > now()) {
            return response()->json([
                'message' => 'Cannot mark future appointments as no-show'
            ], 400);
        }

        // Check if appointment is already marked as no-show
        if ($appointment->statut === 'no_show') {
            return response()->json([
                'message' => 'Appointment is already marked as no-show'
            ], 400);
        }

        $validated = $request->validated();

        $appointment->update([
            'statut' => 'no_show',
        ]);

        // Log the no-show reason if provided
        if (!empty($validated['reason'])) {
            Log::info('Appointment marked as no-show', [
                'appointment_id' => $appointment->id,
                'marked_by' => $user->id,
                'reason' => $validated['reason'],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Appointment marked as no-show',
            'data' => $appointment->load(['doctor.user', 'doctor.speciality', 'patient']),
        ]);
    }

    /**
     * Get no-show statistics for a doctor
     */
    public function getNoShowStats(Request $request): JsonResponse
    {
        $user = auth::user();

        if ($user->role !== 'medecin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $doctor = $user->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $startDate = $request->get('start_date', now()->subMonth());
        $endDate = $request->get('end_date', now());

        $stats = Rendezvous::where('doctor_id', $doctor->id)
            ->whereBetween('date_heure', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN statut = "no_show" THEN 1 ELSE 0 END) as no_shows,
                SUM(CASE WHEN statut = "terminé" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN statut = "annulé" THEN 1 ELSE 0 END) as cancelled
            ')
            ->first();

        $noShowRate = $stats->total > 0 ? round(($stats->no_shows / $stats->total) * 100, 2) : 0;

        // Get repeat no-show patients
        $repeatNoShows = Rendezvous::where('doctor_id', $doctor->id)
            ->where('statut', 'no_show')
            ->whereBetween('date_heure', [$startDate, $endDate])
            ->groupBy('patient_id')
            ->selectRaw('patient_id, COUNT(*) as no_show_count')
            ->having('no_show_count', '>', 1)
            ->with('patient')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'total_appointments' => $stats->total,
                    'no_shows' => $stats->no_shows,
                    'completed' => $stats->completed,
                    'cancelled' => $stats->cancelled,
                    'no_show_rate' => $noShowRate,
                ],
                'repeat_no_shows' => $repeatNoShows->map(function($item) {
                    return [
                        'patient_id' => $item->patient_id,
                        'patient_name' => $item->patient ? $item->patient->prenom . ' ' . $item->patient->nom : 'N/A',
                        'no_show_count' => $item->no_show_count,
                    ];
                }),
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            ]
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

        $validated = $request->validated();

        // Prevent patients from marking appointments as no-show
        if ($user->role === 'patient' && $validated['statut'] === 'no_show') {
            return response()->json([
                'message' => 'Patients cannot mark appointments as no-show'
            ], 403);
        }

        $appointment->statut = $validated['statut'];
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
        $status = $request->query('status');

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

        if ($status) {
            $query->where('statut', $status);
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
                'can_be_cancelled' => $appointment->canBeCancelled(),
                'is_past' => $appointment->date_heure < now(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $appointments,
            'meta' => [
                'total' => $appointments->count(),
                'filters' => array_filter([
                    'doctor_id' => $doctorId,
                    'patient_id' => $patientId,
                    'date' => $date ? $date->format('Y-m-d') : null,
                    'status' => $status,
                ])
            ]
        ]);
    }
}
