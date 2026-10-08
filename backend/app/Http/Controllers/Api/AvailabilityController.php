<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateLeaveRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Doctor;
use App\Models\Leave;
use App\Models\Rendezvous;
use App\Services\DoctorSchedule;
use App\Services\ProfileRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Import Auth facade

class AvailabilityController extends Controller
{
    /**
     * Get a doctor's availability (schedule and leaves)
     */
    public function getAvailability(Request $request, Doctor $doctor): JsonResponse
    {
        // If $doctor is not passed (e.g. for authenticated doctor's own availability)
        if (! $doctor->exists && Auth::check() && Auth::user()->doctor) {
            $doctor = Auth::user()->doctor;
        } elseif (! $doctor->exists) {
            return response()->json(['status' => 'error', 'message' => 'Doctor not specified or found.'], 404);
        }

        $leaves = Leave::where('doctor_id', $doctor->id)
            ->where('end_date', '>=', Carbon::today())
            ->get();

        if ($request->is('api/public/*')) {
            abort_unless($doctor->is_verified && $doctor->is_active && $doctor->user?->status === 'actif' && $doctor->user?->hasVerifiedEmail(), 404);
            $leaves = $leaves->map(fn ($leave) => $leave->only(['start_date', 'end_date']));
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'schedule' => $doctor->horaires ?? [], // Already an array due to model casting
                'schedule_revision' => $request->is('api/public/*') ? null : app(ProfileRevision::class)->schedule($doctor),
                'leaves' => $leaves,
                'can_edit' => (bool) $doctor->is_verified,
                'timezone' => config('app.timezone'),
                'today' => today()->toDateString(),
            ],
        ]);
    }

    /**
     * Update a doctor's schedule
     */
    public function updateSchedule(UpdateScheduleRequest $request): JsonResponse // Doctor will be from Auth
    {
        return DB::transaction(function () use ($request) {
            $doctor = Doctor::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();

            if (! $doctor) {
                return response()->json(['status' => 'error', 'message' => 'Doctor profile not found for authenticated user.'], 404);
            }

            $newSchedule = $request->validated()['schedule'];
            app(ProfileRevision::class)->assertCurrent($request->validated()['expected_schedule_revision'], app(ProfileRevision::class)->schedule($doctor), 'schedule');

            app(DoctorSchedule::class)->assertBookingsFit($doctor, $newSchedule);

            $doctor->update(['horaires' => $newSchedule]); // Already an array, will be JSON encoded by model cast

            Log::info('Schedule updated', ['doctor_id' => $doctor->id]);

            return response()->json([
                'status' => 'success',
                'data' => $newSchedule,
                'schedule_revision' => app(ProfileRevision::class)->schedule($doctor),
            ]);

        }, 3);
    }

    /**
     * Create a leave period for a doctor
     */
    public function createLeave(CreateLeaveRequest $request): JsonResponse // Doctor will be from Auth
    {
        return DB::transaction(function () use ($request) {
            $doctor = Doctor::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();

            if (! $doctor) {
                return response()->json(['status' => 'error', 'message' => 'Doctor profile not found for authenticated user.'], 404);
            }
            $data = $request->validated();

            // Check for conflicts with existing appointments
            $conflicts = Rendezvous::where('doctor_id', $doctor->id)
                ->whereNotIn('statut', ['annulé', 'terminé', 'no_show'])
                ->whereBetween('date_heure', [
                    Carbon::parse($data['start_date'])->startOfDay(),
                    Carbon::parse($data['end_date'])->endOfDay(),
                ])
                ->exists();

            if ($conflicts) {
                Log::warning('Leave creation conflicts', [
                    'doctor_id' => $doctor->id,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'La période de congé entre en conflit avec des rendez-vous existants.',
                ], 409);
            }

            $leave = Leave::create([
                'doctor_id' => $doctor->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'] ?? null,
            ]);

            Log::info('Leave created', ['leave_id' => $leave->id, 'doctor_id' => $doctor->id]);

            return response()->json([
                'status' => 'success',
                'data' => $leave,
            ], 201);

        }, 3);
    }

    /**
     * Delete a leave period
     */
    public function deleteLeave(Request $request, Leave $leave): JsonResponse // Doctor will be from Auth
    {
        $doctor = Auth::user()->doctor;
        if (! $doctor) {
            return response()->json(['status' => 'error', 'message' => 'Doctor profile not found for authenticated user.'], 404);
        }

        if ($leave->doctor_id !== $doctor->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Congé non associé à ce médecin.',
            ], 403);
        }

        $leave->delete();

        Log::info('Leave deleted', ['leave_id' => $leave->id, 'doctor_id' => $doctor->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Congé supprimé.',
        ]);
    }
}
