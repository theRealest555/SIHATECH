<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Language;
use App\Models\Speciality;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogueController extends Controller
{
    private function model(Request $request): string
    {
        return $request->route('catalogue') === 'specialities' ? Speciality::class : Language::class;
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1|max:100000', 'per_page' => 'nullable|integer|min:1|max:100']);
        $model = $this->model($request);
        $query = $model::query();
        if (! empty($filters['search'])) {
            $query->whereRaw("nom LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%']);
        }
        // Counts include archived doctor profiles, which still hold references.
        $query->withCount(['doctors' => fn ($q) => $q->withTrashed()]);
        $page = $query->orderBy('nom')->orderBy('id')->paginate($filters['per_page'] ?? 25);

        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id)
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?int $id = null)
    {
        $model = $this->model($request);
        $table = (new $model)->getTable();
        $rules = ['nom' => ['required', 'string', 'max:255', Rule::unique($table)->ignore($id)]];
        if ($model === Speciality::class) {
            $rules['description'] = 'required|string|max:255';
        }
        if ($id !== null) {
            $rules['expected_nom'] = 'required|string|max:255';
            if ($model === Speciality::class) {
                $rules['expected_description'] = 'required|string|max:255';
            }
        }
        $data = $request->validate($rules);
        try {
            $record = DB::transaction(function () use ($model, $data, $request, $id) {
                $record = $id !== null ? $model::whereKey($id)->lockForUpdate()->firstOrFail() : new $model;
                if ($id !== null && ($record->nom !== $data['expected_nom'] || ($model === Speciality::class && $record->description !== $data['expected_description']))) {
                    abort(409, 'This record changed. Refresh before editing again.');
                }
                $record->nom = $data['nom'];
                if ($model === Speciality::class) {
                    $record->description = $data['description'];
                }
                if (! $record->isDirty() && $record->exists) {
                    return $record;
                }
                $record->save();
                AuditLog::create(['user_id' => $request->user()->id, 'action' => $id !== null ? 'updated_catalogue_record' : 'created_catalogue_record', 'target_type' => $model, 'target_id' => $record->id]);

                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nom' => 'This name is already in use.']);
        }

        return response()->json(['data' => $record], $id !== null ? 200 : 201);
    }
}
