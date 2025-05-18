<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateLeaveRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Doctor;
use App\Models\Leave;
use App\Models\Rendezvous;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AvailabilityController extends Controller
{
    public function getAvailability(Request $request, Doctor $doctor)
    {
        $leaves = Leave::where('doctor_id', $doctor->id)
            ->where('end_date', '>=', Carbon::today())
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'schedule' => json_decode($doctor->horaires ?? '{}', true),
                'leaves' => $leaves,
            ],
        ]);
    }

    public function updateSchedule(UpdateScheduleRequest $request, Doctor $doctor)
    {
        $newSchedule = $request->validated()['schedule'];

        // Check for conflicts with existing appointments
        $conflicts = Rendezvous::where('doctor_id', $doctor->id)
        ->whereNotIn('statut', ['annulé', 'terminé'])
        ->whereDate('date_heure', '>=', Carbon::today())
        ->get()
        ->filter(function ($appointment) use ($newSchedule) {
            $day = strtolower(Carbon::parse($appointment->date_heure)->format('l'));
            $time = Carbon::parse($appointment->date_heure)->format('H:i');
            $dailySchedule = $newSchedule[$day] ?? [];

            foreach ($dailySchedule as $range) {
                [$start, $end] = explode('-', $range);
                if ($time >= $start && $time < $end) {
                    return false; // Appointment fits in new schedule
                }
            }
            return true; // Conflict found
        });

    if ($conflicts->isNotEmpty()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Le nouvel horaire entre en conflit avec des rendez-vous existants.'
        ], 409);
    }

        $doctor->update(['horaires' => json_encode($newSchedule)]);

        Log::info('Schedule updated', ['doctor_id' => $doctor->id]);

        return response()->json([
            'status' => 'success',
            'data' => $newSchedule,
        ]);
    }

    public function createLeave(CreateLeaveRequest $request, Doctor $doctor)
    {
        $data = $request->validated();

        // Check for conflicts with existing appointments
        $conflicts = Rendezvous::where('doctor_id', $doctor->id)
            ->whereNotIn('statut', ['annulé', 'terminé'])
            ->whereBetween('date_heure', [
                Carbon::parse($data['start_date']),
                Carbon::parse($data['end_date'])->endOfDay()
            ])
            ->exists();

        if ($conflicts) {
            Log::warning('Leave creation conflicts', [
                'doctor_id' => $doctor->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date']
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'La période de congé entre en conflit avec des rendez-vous existants.'
            ], 409);
        }

        $leave = Leave::create([
            'doctor_id' => $doctor->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
        ]);

        Log::info('Leave created', ['leave_id' => $leave->id, 'doctor_id' => $doctor->id]);

        return response()->json([
            'status' => 'success',
            'data' => $leave,
        ], 201);
    }

    public function deleteLeave(Request $request, Doctor $doctor, Leave $leave)
    {
        if ($leave->doctor_id !== $doctor->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Congé non associé à ce médecin.'
            ], 403);
        }

        $leave->delete();

        Log::info('Leave deleted', ['leave_id' => $leave->id, 'doctor_id' => $doctor->id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Congé supprimé.'
        ]);
    }
}