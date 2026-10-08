<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\MarkAppointmentAsNoShowRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Leave;
use App\Models\Rendezvous;
use App\Services\AppointmentAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // Fallback to the lowercase English day if not in map, though ideally `horaires` keys match French.
        return $dayMap[strtolower($englishDay)] ?? strtolower($englishDay);
    }

    /**
     * Get available appointment slots for a specific doctor and date
     */
    public function getAvailableSlots(Request $request, Doctor $doctor): JsonResponse
    {
        abort_unless($doctor->is_verified && $doctor->is_active && $doctor->user?->status === 'actif' && $doctor->user?->hasVerifiedEmail(), 404);
        $validated = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:today']]);
        $date = Carbon::parse($validated['date'] ?? today()->toDateString());
        $slots = app(AppointmentAvailability::class)->slots($doctor, $date);
        $onLeave = Leave::where('doctor_id', $doctor->id)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists();

        return response()->json(['status' => 'success', 'data' => $slots, 'meta' => ['doctor_id' => $doctor->id, 'date' => $date->toDateString(), 'available_slots' => count($slots), 'is_on_leave' => $onLeave, 'timezone' => config('app.timezone')]]);
    }

    /**
     * Book a new appointment
     */
    public function bookAppointment(BookAppointmentRequest $request, int $doctorId): JsonResponse
    {
        return DB::transaction(function () use ($request, $doctorId) {
            $user = $request->user();
            // Lock stable rows, rather than a possibly empty appointment query.
            $patient = $user->patient()->lockForUpdate()->firstOrFail();
            $doctor = Doctor::whereKey($doctorId)->lockForUpdate()->firstOrFail();
            $date = Carbon::parse($request->validated()['date_heure']);
            if ($date->second !== 0 || ! in_array($date->format('H:i'), app(AppointmentAvailability::class)->slots($doctor, $date), true)) {
                return response()->json(['message' => 'This time slot is no longer available'], 409);
            }
            $overlap = Rendezvous::where('patient_id', $patient->id)->where('statut', '!=', 'annulé')
                ->where('date_heure', '>', $date->copy()->subMinutes(30))
                ->where('date_heure', '<', $date->copy()->addMinutes(30))->exists();
            if ($overlap) {
                return response()->json(['message' => 'You already have an appointment at this time.'], 409);
            }
            $appointment = Rendezvous::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'date_heure' => $date, 'statut' => 'en_attente']);

            return response()->json(['status' => 'success', 'data' => $appointment->load(['doctor.user', 'doctor.speciality'])], 201);
        }, 3);
    }

    /**
     * Update an appointment's status
     */
    public function updateAppointmentStatus(UpdateAppointmentStatusRequest $request, Rendezvous $rendezvous): JsonResponse
    {
        return DB::transaction(function () use ($request, $rendezvous) {
            Doctor::whereKey($rendezvous->doctor_id)->lockForUpdate()->firstOrFail();
            $appointment = Rendezvous::whereKey($rendezvous->id)->lockForUpdate()->firstOrFail();
            $user = $request->user();
            if (($user->role === 'patient' && $appointment->patient_id !== $user->patient?->id)
                || ($user->role === 'medecin' && $appointment->doctor_id !== $user->doctor?->id)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $status = $request->validated()['statut'];
            $transitions = ['en_attente' => ['confirmé', 'annulé'], 'confirmé' => ['annulé', 'terminé']];
            if (! in_array($status, $transitions[$appointment->statut] ?? [], true)) {
                return response()->json(['message' => 'This appointment cannot make that status transition.'], 409);
            }
            if (($status === 'annulé' && ! $appointment->canBeCancelled()) || ($status === 'terminé' && $appointment->date_heure->isFuture())) {
                return response()->json(['message' => 'This action is not available at this time.'], 409);
            }
            $appointment->update(['statut' => $status]);

            return response()->json(['status' => 'success', 'data' => $appointment->load(['doctor.user', 'doctor.speciality', 'patient.user'])]);
        }, 3);
    }

    /**
     * Mark an appointment as no-show
     */
    public function markAsNoShow(MarkAppointmentAsNoShowRequest $request, int $id): JsonResponse
    {
        $candidate = Rendezvous::findOrFail($id);

        return DB::transaction(function () use ($request, $candidate) {
            Doctor::whereKey($candidate->doctor_id)->lockForUpdate()->firstOrFail();
            $appointment = Rendezvous::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            $user = $request->user();
            if ($user->role === 'medecin' && $appointment->doctor_id !== $user->doctor?->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if ($appointment->date_heure->isFuture()) {
                return response()->json(['message' => 'Cannot mark future appointments as no-show'], 400);
            }
            if ($appointment->statut !== 'confirmé') {
                return response()->json(['message' => 'Only a confirmed appointment can be marked as no-show. Refresh its current status.'], 409);
            }
            $appointment->update(['statut' => 'no_show']);
            AuditLog::create([
                'user_id' => $user->id, 'action' => 'marked_appointment_no_show',
                'target_type' => Rendezvous::class, 'target_id' => $appointment->id,
                'metadata' => json_encode(['from' => 'confirmé', 'to' => 'no_show'], JSON_THROW_ON_ERROR),
            ]);

            return response()->json([
                'status' => 'success', 'message' => 'Appointment marked as no-show',
                'data' => $appointment->load(['doctor.user', 'doctor.speciality', 'patient.user']),
            ]);
        }, 3);
    }

    /**
     * Get no-show statistics for a doctor
     */
    public function getNoShowStats(Request $request): JsonResponse
    {
        $user = Auth::user(); // Ensure Auth facade is imported

        if ($user->role !== 'medecin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $doctor = $user->doctor;

        if (! $doctor) {
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
            ->with('patient.user')
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
                'repeat_no_shows' => $repeatNoShows->map(function ($item) {
                    return [
                        'patient_id' => $item->patient_id,
                        'patient_name' => $item->patient ? $item->patient->user->prenom.' '.$item->patient->user->nom : 'N/A',
                        'no_show_count' => $item->no_show_count,
                    ];
                }),
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }

    /**
     * Update an appointment's status by ID
     */
    public function updateStatus(UpdateAppointmentStatusRequest $request, int $id): JsonResponse
    {
        return $this->updateAppointmentStatus($request, Rendezvous::findOrFail($id));
    }

    /**
     * Get appointments with filtering options
     */
    public function getAppointments(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['patient', 'medecin', 'admin'], true), 403);
        abort_if($user->role === 'admin' && ! $user->isApprovedAdmin(), 403);
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:en_attente,confirmé,annulé,terminé,no_show'],
            'period' => ['nullable', 'in:upcoming,past,cancelled,all'],
            'doctor_id' => ['nullable', 'integer', 'min:1'],
            'patient_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Rendezvous::query()->with(['doctor.user', 'doctor.speciality', 'patient.user']);
        if ($user->role === 'patient') {
            $query->where('patient_id', $user->patient()->firstOrFail()->id);
        } elseif ($user->role === 'medecin') {
            $query->where('doctor_id', $user->doctor()->firstOrFail()->id);
        } else {
            if (! empty($filters['doctor_id'])) {
                $query->where('doctor_id', $filters['doctor_id']);
            }
            if (! empty($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }
        }
        if (! empty($filters['date'])) {
            $query->whereDate('date_heure', $filters['date']);
        }
        if (! empty($filters['status'])) {
            $query->where('statut', $filters['status']);
        }
        $period = $filters['period'] ?? 'all';
        if ($period === 'upcoming') {
            $query->where('date_heure', '>', now())->whereIn('statut', ['en_attente', 'confirmé']);
        } elseif ($period === 'past') {
            $query->where('date_heure', '<=', now());
        } elseif ($period === 'cancelled') {
            $query->where('statut', 'annulé');
        }
        $direction = $period === 'upcoming' ? 'asc' : 'desc';
        $appointments = $query->orderBy('date_heure', $direction)->orderBy('id', $direction)
            ->paginate($filters['per_page'] ?? 15);
        $data = $appointments->getCollection()->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'patient_name' => trim(($appointment->patient?->user?->prenom ?? '').' '.($appointment->patient?->user?->nom ?? '')),
                'doctor_name' => $appointment->doctor?->full_name ?? 'Doctor unavailable',
                'speciality' => $appointment->doctor?->speciality?->nom,
                'date_heure' => $appointment->date_heure->format('Y-m-d H:i:s'),
                'starts_at' => $appointment->date_heure->toIso8601String(),
                'statut' => $appointment->statut,
                'can_be_cancelled' => $appointment->canBeCancelled(),
                'is_past' => $appointment->date_heure->lte(now()),
            ];
        });

        return response()->json([
            'status' => 'success', 'data' => $data,
            'meta' => [
                'total' => $appointments->total(), 'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(), 'per_page' => $appointments->perPage(),
                'timezone' => config('app.timezone'), 'filters' => array_filter($filters),
            ],
        ]);
    }
}
