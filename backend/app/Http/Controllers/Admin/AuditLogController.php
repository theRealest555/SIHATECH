<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'user_id' => 'nullable|integer|min:1',
            'action' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9_]+$/',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = AuditLog::query()->with('actor:id,prenom,nom');
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['start_date'])->startOfDay());
        }
        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['end_date'])->addDay()->startOfDay());
        }
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 25);
        $rows = $page->getCollection()->map(function (AuditLog $log) {
            $metadata = json_decode($log->metadata ?? '', true);
            // Legacy metadata can contain arbitrary data. Expose only known decision fields.
            $details = [];
            if (is_array($metadata)) {
                $statuses = ['actif' => 'Active', 'inactif' => 'Inactive', 'en_attente' => 'Pending'];
                if ($log->action === 'updated_user_status' && in_array($log->target_type, ['user', 'App\\Models\\User'], true)
                    && is_string($metadata['from'] ?? null) && is_string($metadata['to'] ?? null)
                    && isset($statuses[$metadata['from']], $statuses[$metadata['to']])) {
                    $details['transition'] = 'Account status: '.$statuses[$metadata['from']].' → '.$statuses[$metadata['to']];
                    if (($metadata['access_revoked'] ?? null) === true && $metadata['to'] !== 'actif') {
                        $details['access_revoked'] = true;
                    }
                }
                if ($log->action === 'updated_admin_status' && in_array($log->target_type, ['admin', 'App\\Models\\Admin'], true)
                    && in_array($metadata['from'] ?? null, [0, 1], true) && in_array($metadata['to'] ?? null, [0, 1], true)) {
                    $approval = [0 => 'Not approved', 1 => 'Approved'];
                    $details['transition'] = 'Administrator approval: '.$approval[$metadata['from']].' → '.$approval[$metadata['to']];
                    if (($metadata['access_revoked'] ?? null) === true && $metadata['to'] === 0) {
                        $details['access_revoked'] = true;
                    }
                }
                if ($log->action === 'marked_appointment_no_show' && $log->target_type === 'App\\Models\\Rendezvous'
                    && ($metadata['from'] ?? null) === 'confirmé' && ($metadata['to'] ?? null) === 'no_show') {
                    $details['transition'] = 'Confirmed → No-show';
                }
                if (isset($metadata['reason']) && is_string($metadata['reason'])) {
                    $details['reason'] = mb_substr($metadata['reason'], 0, 2000);
                }
                if (isset($metadata['document_id']) && is_numeric($metadata['document_id'])) {
                    $details['document_id'] = (int) $metadata['document_id'];
                }
            }
            $target = match ($log->target_type) {
                'user', 'App\\Models\\User' => 'User',
                'admin', 'App\\Models\\Admin' => 'Administrator',
                'App\\Models\\Doctor', 'doctor' => 'Doctor',
                'App\\Models\\Document', 'document' => 'Document',
                'App\\Models\\Avis', 'review' => 'Review',
                'App\Models\Speciality' => 'Speciality',
                'App\Models\Language' => 'Language',
                'App\Models\Abonnement' => 'Subscription plan',
                'App\Models\Rendezvous' => 'Appointment',
                default => 'Other record',
            };

            return ['id' => $log->id, 'created_at' => $log->created_at?->toIso8601String(), 'action' => $log->action,
                'actor' => $log->actor ? ['id' => $log->actor->id, 'name' => trim($log->actor->prenom.' '.$log->actor->nom)] : null,
                'target' => ['type' => $target, 'id' => $log->target_id], 'details' => (object) $details];
        });

        return response()->json(['data' => $rows, 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage(), 'timezone' => config('app.timezone')]], 200, ['Cache-Control' => 'private, no-store']);
    }
}
