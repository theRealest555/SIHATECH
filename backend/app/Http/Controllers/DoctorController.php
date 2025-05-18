<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Doctor;
use App\Models\Speciality;
use App\Models\Leave;

class DoctorController extends Controller
{
    public function index()
{
    $doctors = Doctor::with(['user', 'speciality'])->get()->map(fn($doctor) => [
        'id' => $doctor->id,
        'name' => $doctor->user ? ($doctor->user->prenom . ' ' . $doctor->user->nom) : 'N/A',
        'speciality' => $doctor->speciality ? $doctor->speciality->nom : 'N/A',
    ]);
    return response()->json(['data' => $doctors]);
}

    public function specialities()
    {
        $specialities = Speciality::pluck('nom');
        return response()->json(['data' => $specialities]);
    }

    public function locations()
{
    $locations = Doctor::with('user')
        ->distinct('user.adresse') // Ensure distinct addresses
        ->get()
        ->pluck('user.adresse')
        ->filter()
        ->values();
    return response()->json(['data' => $locations]);
}

    public function search(Request $request)
    {
        $speciality = $request->query('speciality');
        $location = $request->query('location');
        $date = $request->query('date', now()->toDateString());

        $query = Doctor::query()
            ->select('doctors.*')
            ->with(['user', 'speciality'])
            ->when($speciality, fn($q) => $q->whereHas('speciality', fn($q) => $q->where('nom', $speciality)))
            ->when($location, fn($q) => $q->whereHas('user', fn($q) => $q->where('adresse', $location)));

        $doctors = $query->get()->map(function ($doctor) use ($date) {
            $slots = $this->getAvailableSlots($doctor, $date);
            return [
                'id' => $doctor->id,
                'name' => $doctor->user ? ($doctor->user->prenom . ' ' . $doctor->user->nom) : 'N/A',
                'speciality' => $doctor->speciality ? $doctor->speciality->nom : 'N/A',
                'location' => $doctor->user ? $doctor->user->adresse : 'N/A',
                'available_slots' => $slots,
            ];
        });

        return response()->json(['data' => $doctors]);
    }

    public function availability($doctorId)
    {
        try {
            $doctor = Doctor::findOrFail($doctorId);
            $schedule = $doctor->availabilities->groupBy('day_of_week')->map->pluck('time_range');
            $leaves = $doctor->leaves;
            return response()->json(['data' => ['schedule' => $schedule, 'leaves' => $leaves]]);
        } catch (\Exception $e) {
            \Log::error('Error fetching availability for doctor ID ' . $doctorId . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch availability'], 500);
        }
    }

    public function slots($doctorId, Request $request)
    {
        try {
            $date = $request->query('date', now()->toDateString());
            $doctor = Doctor::findOrFail($doctorId);
            $slots = $this->getAvailableSlots($doctor, $date);
            return response()->json(['data' => $slots]);
        } catch (\Exception $e) {
            \Log::error('Error fetching slots for doctor ID ' . $doctorId . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch slots'], 500);
        }
    }

public function getAvailableSlots($doctor, $date)
{
    try {
        $dayOfWeek = strtolower(now()->parse($date)->format('l'));
        $leaves = $doctor->leaves->filter(fn($leave) => now()->parse($date)->between($leave->start_date, $leave->end_date));
        if ($leaves->isNotEmpty()) {
            \Log::info('No slots available due to leave', [
                'doctor_id' => $doctor->id,
                'date' => $date,
                'leaves' => $leaves->toArray(),
            ]);
            return [];
        }

        $schedule = $doctor->availabilities->where('day_of_week', $dayOfWeek);
        $appointments = $doctor->appointments->filter(fn($appt) => now()->parse($appt->start_time)->isSameDay(now()->parse($date)));

        $slots = [];
        foreach ($schedule as $range) {
            if (!$range->time_range) {
                continue;
            }
            $timeRange = explode('-', $range->time_range);
            if (count($timeRange) !== 2) {
                continue;
            }
            $start = now()->parse("$date {$timeRange[0]}");
            $end = now()->parse("$date {$timeRange[1]}");
            while ($start < $end) {
                $slotEnd = $start->copy()->addMinutes(30);
                if (!$appointments->first(fn($appt) => now()->parse($appt->start_time)->eq($start))) {
                    $slots[] = $start->format('H:i');
                }
                $start = $slotEnd;
            }
        }
        \Log::info('Slots generated', [
            'doctor_id' => $doctor->id,
            'date' => $date,
            'day_of_week' => $dayOfWeek,
            'slots' => $slots,
        ]);
        return $slots;
    } catch (\Exception $e) {
        \Log::error('Error in getAvailableSlots for doctor ID ' . $doctor->id . ': ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

public function updateSchedule(Request $request, $doctorId)
{
    $request->validate([
        'monday' => 'array',
        'tuesday' => 'array',
        'wednesday' => 'array',
        'thursday' => 'array',
        'friday' => 'array',
        'saturday' => 'array',
        'sunday' => 'array',
    ]);

    $doctor = Doctor::findOrFail($doctorId);

    $doctor->availabilities()->delete();

    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        if ($request->has($day)) {
            foreach ($request->input($day) as $timeRange) {
                $doctor->availabilities()->create([
                    'day_of_week' => $day,
                    'time_range' => $timeRange,
                ]);
            }
        }
    }

    return response()->json(['message' => 'Schedule updated successfully']);
}

    public function createLeave(Request $request, $doctorId)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        $doctor = Doctor::findOrFail($doctorId);
        $leave = $doctor->leaves()->create($request->only(['start_date', 'end_date', 'reason']));

        return response()->json(['data' => $leave], 201);
    }

    public function deleteLeave($doctorId, $leaveId, Request $request)
{
    \Log::info('deleteLeave called', ['doctorId' => $doctorId, 'leaveId' => $leaveId]);
    $user = auth()->guard('sanctum')->user();
    \Log::info('Authenticated user', ['user' => $user ? $user->toArray() : null]);

    if ($user->id != $doctorId || $user->role !== 'medecin') {
        \Log::warning('Unauthorized access attempt', ['user_id' => $user->id, 'role' => $user->role]);
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $leave = Leave::where('id', $leaveId)->where('doctor_id', $doctorId)->first();
    \Log::info('Leave query result', ['leave' => $leave ? $leave->toArray() : null]);

    if (!$leave) {
        \Log::warning('Leave not found', ['doctorId' => $doctorId, 'leaveId' => $leaveId]);
        return response()->json(['message' => 'Leave not found'], 404);
    }

    $leave->delete();
    \Log::info('Leave deleted', ['leaveId' => $leaveId]);

    return response()->json([
        'status' => 'success',
        'message' => 'Leave deleted successfully'
    ]);
}
}