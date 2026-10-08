<?php

namespace App\Http\Controllers;

use App\Http\Requests\Doctor\UpdateDoctorLanguagesRequest;
use App\Http\Requests\SearchDoctorsAdvancedRequest;
use App\Models\Doctor;
use App\Models\Language;
use App\Models\Leave;
use App\Models\Location;
use App\Models\Speciality;
use App\Services\AppointmentAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DoctorController extends Controller
{
    public function index(): JsonResponse
    {
        $doctors = Doctor::with(['user', 'speciality', 'languages', 'location'])
            ->verified()
            ->active()
            ->get()
            ->map(fn ($doctor) => [
                'id' => $doctor->id,
                'name' => $doctor->full_name,
                'speciality' => $doctor->speciality ? $doctor->speciality->nom : 'N/A',
                'location' => $doctor->location ? $doctor->location->name : 'N/A',
                'languages' => $doctor->languages->pluck('nom'),
                'rating' => [
                    'average' => $doctor->average_rating,
                    'total_reviews' => $doctor->total_reviews,
                ],
                'photo' => $doctor->user ? $doctor->user->photo : null,
                'is_verified' => $doctor->is_verified, // Added this
                'is_active' => $doctor->is_active,   // Added this
            ]);

        return response()->json(['data' => $doctors]);
    }

    public function specialities(): JsonResponse
    {
        $specialities = Speciality::orderBy('nom')->get();

        return response()->json(['data' => $specialities]);
    }

    public function languages(): JsonResponse
    {
        $languages = Language::orderBy('nom')->get();

        return response()->json(['data' => $languages]);
    }

    public function locations(): JsonResponse
    {
        $locations = Location::orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();

        return response()->json(['data' => $locations]);
    }

    public function search(SearchDoctorsAdvancedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 15;
        $sortBy = $validated['sort_by'] ?? 'average_rating';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        $query = Doctor::query()
            ->with(['user', 'speciality', 'languages', 'location'])
            ->verified()
            ->active()
            ->search($validated); // scopeSearch is in Doctor model

        switch ($sortBy) {
            case 'rating':
                $query->orderBy('average_rating', $sortOrder);
                break;
            case 'name':
                $query->join('users', 'doctors.user_id', '=', 'users.id')
                    ->orderBy('users.nom', $sortOrder) // Assuming you want to sort by last name
                    ->orderBy('users.prenom', $sortOrder) // Then by first name
                    ->select('doctors.*'); // Important to select from doctors table
                break;
            case 'created_at':
                $query->orderBy('doctors.created_at', $sortOrder); // Specify table for created_at
                break;
            default:
                $query->orderBy('average_rating', 'desc');
        }

        $doctors = $query->paginate($perPage);

        $doctors->getCollection()->transform(function ($doctor) use ($validated) {
            $doctorData = [
                'id' => $doctor->id,
                'name' => $doctor->full_name,
                'speciality' => $doctor->speciality ? $doctor->speciality->nom : 'N/A',
                'location' => $doctor->location ? $doctor->location->name : 'N/A',
                'languages' => $doctor->languages->pluck('nom'),
                'rating' => [
                    'average' => $doctor->average_rating,
                    'total_reviews' => $doctor->total_reviews,
                    'formatted' => $doctor->formatted_rating,
                ],
                'photo' => $doctor->user ? $doctor->user->photo : null,
                'description' => $doctor->description,
                'is_verified' => $doctor->is_verified,
                'is_active' => $doctor->is_active, // Added this line
            ];

            if (! empty($validated['date'])) {
                $doctorData['available_slots'] = $this->getAvailableSlots($doctor, $validated['date']);
            }

            return $doctorData;
        });

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
            'timezone' => config('app.timezone'),
            'today' => today()->toDateString(),
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

    public function show(int $doctorId): JsonResponse
    {
        $doctor = Doctor::with(['user', 'speciality', 'languages', 'location', 'documents' => function ($query) {
            $query->where('status', 'approved');
        }])
            ->verified()->active()
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
            ],
            'meta' => ['timezone' => config('app.timezone'), 'today' => today()->toDateString()],
        ]);
    }

    public function updateLanguages(UpdateDoctorLanguagesRequest $request): JsonResponse
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found',
            ], 404);
        }

        $validated = $request->validated();

        $doctor->languages()->sync($validated['language_ids']);

        return response()->json([
            'status' => 'success',
            'message' => 'Languages updated successfully',
            'data' => [
                'languages' => $doctor->languages()->get(),
            ],
        ]);
    }

    public function availability(int $doctorId): JsonResponse
    {
        try {
            $doctor = Doctor::findOrFail($doctorId);
            $schedule = $doctor->horaires ?? []; // Already an array due to model casting
            $leaves = $doctor->leaves;

            return response()->json([
                'data' => [
                    'schedule' => $schedule,
                    'leaves' => $leaves,
                    'languages' => $doctor->languages,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching availability for doctor ID '.$doctorId.': '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to fetch availability'], 500);
        }
    }

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
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching slots for doctor ID '.$doctorId.': '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to fetch slots'], 500);
        }
    }

    public function statistics(int $doctorId): JsonResponse
    {
        $doctor = Doctor::verified()->active()->findOrFail($doctorId);

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

    protected function getAvailableSlots(Doctor $doctor, string $date): array
    {
        return app(AppointmentAvailability::class)->slots($doctor, Carbon::parse($date));
    }

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
            if ($request->has($day) && ! empty($request->input($day))) {
                // Assuming input is a comma-separated string of time ranges
                $horaires[$day] = array_map('trim', explode(',', $request->input($day)));
            } else {
                $horaires[$day] = []; // Ensure empty array if no schedule for the day
            }
        }

        $doctor->update(['horaires' => $horaires]); // Already an array, will be JSON encoded by model cast

        return response()->json([
            'message' => 'Schedule updated successfully',
            'data' => $horaires,
        ]);
    }

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

    public function deleteLeave(int $doctorId, int $leaveId, Request $request): JsonResponse
    {
        Log::info('deleteLeave called', ['doctorId' => $doctorId, 'leaveId' => $leaveId]);

        $user = Auth::user();
        Log::info('Authenticated user', ['user' => $user ? json_decode(json_encode($user), true) : null]);

        if (! $user || ($user->role === 'medecin' && $user->doctor->id != $doctorId) && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $leave = Leave::where('id', $leaveId)->where('doctor_id', $doctorId)->first();
        Log::info('Leave query result', ['leave' => $leave ? $leave->toArray() : null]);

        if (! $leave) {
            Log::warning('Leave not found', ['doctorId' => $doctorId, 'leaveId' => $leaveId]);

            return response()->json(['message' => 'Leave not found'], 404);
        }

        $leave->delete();
        Log::info('Leave deleted', ['leaveId' => $leaveId]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave deleted successfully',
        ]);
    }
}
