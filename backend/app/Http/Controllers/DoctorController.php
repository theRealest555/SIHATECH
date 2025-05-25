<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\SearchDoctorsAdvancedRequest;
use App\Http\Requests\Doctor\UpdateDoctorLanguagesRequest;
use App\Models\Doctor;
use App\Models\Speciality;
use App\Models\Leave;
use App\Models\Availability;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DoctorController extends Controller
{
    /**
     * Display a listing of doctors.
     */
    public function index(): JsonResponse
    {
        $doctors = Doctor::with(['user', 'speciality', 'languages'])
            ->verified()
            ->active()
            ->get()
            ->map(fn($doctor) => [
                'id' => $doctor->id,
                'name' => $doctor->full_name,
                'speciality' => $doctor->speciality ? $doctor->speciality->nom : 'N/A',
                'location' => $doctor->user ? $doctor->user->adresse : 'N/A',
                'languages' => $doctor->languages->pluck('nom'),
                'rating' => [
                    'average' => $doctor->average_rating,
                    'total_reviews' => $doctor->total_reviews,
                ],
                'photo' => $doctor->user ? $doctor->user->photo : null,
            ]);

        return response()->json(['data' => $doctors]);
    }

    /**
     * Get all specialities.
     */
    public function specialities(): JsonResponse
    {
        $specialities = Speciality::orderBy('nom')->get();
        return response()->json(['data' => $specialities]);
    }

    /**
     * Get all languages.
     */
    public function languages(): JsonResponse
    {
        $languages = Language::orderBy('nom')->get();
        return response()->json(['data' => $languages]);
    }

    /**
     * Get all doctor locations.
     */
    public function locations(): JsonResponse
    {
        $locations = Doctor::with('user')
            ->verified()
            ->active()
            ->distinct('user.adresse')
            ->get()
            ->pluck('user.adresse')
            ->filter()
            ->unique()
            ->values();

        return response()->json(['data' => $locations]);
    }

    /**
     * Advanced search for doctors with filters.
     */
    public function search(SearchDoctorsAdvancedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 15;
        $sortBy = $validated['sort_by'] ?? 'average_rating';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        $query = Doctor::query()
            ->with(['user', 'speciality', 'languages'])
            ->verified()
            ->active()
            ->search($validated);

        // Apply sorting
        switch ($sortBy) {
            case 'rating':
                $query->orderBy('average_rating', $sortOrder);
                break;
            case 'name':
                $query->join('users', 'doctors.user_id', '=', 'users.id')
                    ->orderBy('users.nom', $sortOrder)
                    ->select('doctors.*');
                break;
            case 'created_at':
                $query->orderBy('created_at', $sortOrder);
                break;
            default:
                $query->orderBy('average_rating', 'desc');
        }

        // Get paginated results
        $doctors = $query->paginate($perPage);

        // Transform the results
        $doctors->getCollection()->transform(function ($doctor) use ($validated) {
            $doctorData = [
                'id' => $doctor->id,
                'name' => $doctor->full_name,
                'speciality' => $doctor->speciality ? $doctor->speciality->nom : 'N/A',
                'location' => $doctor->user ? $doctor->user->adresse : 'N/A',
                'languages' => $doctor->languages->pluck('nom'),
                'rating' => [
                    'average' => $doctor->average_rating,
                    'total_reviews' => $doctor->total_reviews,
                    'formatted' => $doctor->formatted_rating,
                ],
                'photo' => $doctor->user ? $doctor->user->photo : null,
                'description' => $doctor->description,
                'is_verified' => $doctor->is_verified,
            ];

            // If date is provided, add available slots for that date
            if (!empty($validated['date'])) {
                $doctorData['available_slots'] = $this->getAvailableSlots($doctor, $validated['date']);
            }

            return $doctorData;
        });

        // Add search metadata
        $metadata = [
            'filters_applied' => array_filter([
                'speciality_id' => $validated['speciality_id'] ?? null,
                'location' => $validated['location'] ?? null,
                'language_ids' => $validated['language_ids'] ?? null,
                'min_rating' => $validated['min_rating'] ?? null,
                'date' => $validated['date'] ?? null,
            ]),
            'sort' => [
                'by' => $sortBy,
                'order' => $sortOrder,
            ],
        ];

        return response()->json([
            'data' => $doctors->items(),
            'meta' => array_merge($metadata, [
                'current_page' => $doctors->currentPage(),
                'from' => $doctors->firstItem(),
                'last_page' => $doctors->lastPage(),
                'per_page' => $doctors->perPage(),
                'to' => $doctors->lastItem(),
                'total' => $doctors->total(),
            ]),
            'links' => [
                'first' => $doctors->url(1),
                'last' => $doctors->url($doctors->lastPage()),
                'prev' => $doctors->previousPageUrl(),
                'next' => $doctors->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get a doctor's details.
     */
    public function show(int $doctorId): JsonResponse
    {
        $doctor = Doctor::with(['user', 'speciality', 'languages', 'documents' => function($query) {
            $query->where('status', 'approved');
        }])
        ->findOrFail($doctorId);

        return response()->json([
            'data' => [
                'id' => $doctor->id,
                'name' => $doctor->full_name,
                'email' => $doctor->user->email,
                'phone' => $doctor->user->telephone,
                'address' => $doctor->user->adresse,
                'speciality' => $doctor->speciality,
                'languages' => $doctor->languages,
                'description' => $doctor->description,
                'rating' => [
                    'average' => $doctor->average_rating,
                    'total_reviews' => $doctor->total_reviews,
                    'formatted' => $doctor->formatted_rating,
                ],
                'schedule' => $doctor->horaires,
                'is_verified' => $doctor->is_verified,
                'is_active' => $doctor->is_active,
                'photo' => $doctor->user->photo,
                'documents_count' => $doctor->documents->count(),
            ]
        ]);
    }

    /**
     * Update doctor's languages.
     */
    public function updateLanguages(UpdateDoctorLanguagesRequest $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found'
            ], 404);
        }

        $validated = $request->validated();

        // Sync languages
        $doctor->languages()->sync($validated['language_ids']);

        return response()->json([
            'status' => 'success',
            'message' => 'Languages updated successfully',
            'data' => [
                'languages' => $doctor->languages()->get()
            ]
        ]);
    }

    /**
     * Get a doctor's availability.
     */
    public function availability(int $doctorId): JsonResponse
    {
        try {
            $doctor = Doctor::findOrFail($doctorId);
            $schedule = $doctor->horaires ?? [];
            $leaves = $doctor->leaves;

            return response()->json([
                'data' => [
                    'schedule' => $schedule,
                    'leaves' => $leaves,
                    'languages' => $doctor->languages,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching availability for doctor ID ' . $doctorId . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to fetch availability'], 500);
        }
    }

    /**
     * Get available slots for a doctor on a specific date.
     */
    public function slots(int $doctorId, Request $request): JsonResponse
    {
        try {
            $date = $request->query('date', now()->toDateString());
            $doctor = Doctor::findOrFail($doctorId);
            $slots = $this->getAvailableSlots($doctor, $date);

            return response()->json([
                'data' => $slots,
                'meta' => [
                    'doctor_id' => $doctorId,
                    'date' => $date,
                    'total_slots' => count($slots),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching slots for doctor ID ' . $doctorId . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Failed to fetch slots'], 500);
        }
    }

    /**
     * Get doctor statistics (summary).
     */
    public function statistics(int $doctorId): JsonResponse
    {
        $doctor = Doctor::findOrFail($doctorId);

        $stats = [
            'total_appointments' => $doctor->appointments()->count(),
            'completed_appointments' => $doctor->appointments()->where('statut', 'terminé')->count(),
            'upcoming_appointments' => $doctor->appointments()
                ->where('date_heure', '>', now())
                ->whereIn('statut', ['confirmé', 'en_attente'])
                ->count(),
            'total_patients' => $doctor->appointments()->distinct('patient_id')->count('patient_id'),
            'rating' => [
                'average' => $doctor->average_rating,
                'total_reviews' => $doctor->total_reviews,
            ],
            'languages' => $doctor->languages->pluck('nom'),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Helper method to get available slots for a doctor.
     */
    protected function getAvailableSlots(Doctor $doctor, string $date): array
    {
        try {
            $parsedDate = Carbon::parse($date);
            $dayOfWeek = strtolower($parsedDate->format('l'));

            // Map English to French days
            $dayMap = [
                'monday' => 'lundi',
                'tuesday' => 'mardi',
                'wednesday' => 'mercredi',
                'thursday' => 'jeudi',
                'friday' => 'vendredi',
                'saturday' => 'samedi',
                'sunday' => 'dimanche',
            ];

            $frenchDay = $dayMap[$dayOfWeek] ?? $dayOfWeek;

            // Check if doctor is on leave
            $isOnLeave = $doctor->leaves()
                ->where('start_date', '<=', $parsedDate)
                ->where('end_date', '>=', $parsedDate)
                ->exists();

            if ($isOnLeave) {
                return [];
            }

            // Get doctor's schedule for the day
            $horaires = $doctor->horaires ?? [];
            $dailySchedule = $horaires[$frenchDay] ?? [];

            if (empty($dailySchedule)) {
                return [];
            }

            $slots = [];
            $slotDuration = 30; // minutes

            // Parse schedule string
            if (is_string($dailySchedule)) {
                $timeRanges = explode(',', $dailySchedule);
            } else {
                $timeRanges = $dailySchedule;
            }

            foreach ($timeRanges as $timeRange) {
                $timeRange = trim($timeRange);
                if (strpos($timeRange, '-') === false) {
                    continue;
                }

                [$start, $end] = explode('-', $timeRange);
                $start = trim($start);
                $end = trim($end);

                $startTime = Carbon::parse($date . ' ' . $start);
                $endTime = Carbon::parse($date . ' ' . $end);

                while ($startTime < $endTime) {
                    $slots[] = $startTime->format('H:i');
                    $startTime->addMinutes($slotDuration);
                }
            }

            // Remove booked slots
            $bookedSlots = $doctor->appointments()
                ->whereDate('date_heure', $parsedDate)
                ->whereNotIn('statut', ['annulé', 'no_show'])
                ->pluck('date_heure')
                ->map(fn($dt) => Carbon::parse($dt)->format('H:i'))
                ->toArray();

            return array_values(array_diff($slots, $bookedSlots));
        } catch (\Exception $e) {
            Log::error('Error in getAvailableSlots: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update a doctor's schedule.
     */
    public function updateSchedule(Request $request, int $doctorId): JsonResponse
    {
        $request->validate([
            'lundi' => 'nullable|string',
            'mardi' => 'nullable|string',
            'mercredi' => 'nullable|string',
            'jeudi' => 'nullable|string',
            'vendredi' => 'nullable|string',
            'samedi' => 'nullable|string',
            'dimanche' => 'nullable|string',
        ]);

        $doctor = Doctor::findOrFail($doctorId);

        $horaires = [];
        $days = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

        foreach ($days as $day) {
            if ($request->has($day) && !empty($request->input($day))) {
                $horaires[$day] = $request->input($day);
            }
        }

        $doctor->update(['horaires' => $horaires]);

        return response()->json([
            'message' => 'Schedule updated successfully',
            'data' => $horaires
        ]);
    }

    /**
     * Create a leave period for a doctor.
     */
    public function createLeave(Request $request, int $doctorId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:255',
        ]);

        $doctor = Doctor::findOrFail($doctorId);
        $leave = $doctor->leaves()->create($request->only(['start_date', 'end_date', 'reason']));

        return response()->json(['data' => $leave], 201);
    }

    /**
     * Delete a leave period.
     */
    public function deleteLeave(int $doctorId, int $leaveId, Request $request): JsonResponse
    {
        Log::info('deleteLeave called', ['doctorId' => $doctorId, 'leaveId' => $leaveId]);

        $user = auth()->guard('sanctum')->user();
        Log::info('Authenticated user', ['user' => $user ? json_decode(json_encode($user), true) : null]);

        if ($user && $user->id != $doctorId && $user->role !== 'medecin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $leave = Leave::where('id', $leaveId)->where('doctor_id', $doctorId)->first();
        Log::info('Leave query result', ['leave' => $leave ? $leave->toArray() : null]);

        if (!$leave) {
            Log::warning('Leave not found', ['doctorId' => $doctorId, 'leaveId' => $leaveId]);
            return response()->json(['message' => 'Leave not found'], 404);
        }

        $leave->delete();
        Log::info('Leave deleted', ['leaveId' => $leaveId]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave deleted successfully'
        ]);
    }
}
