<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminStatusRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\Admin as AdminModel;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\AccountCredentials;
use App\Services\AccountStatusRevision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:patient,medecin,admin'],
            'status' => ['nullable', 'in:actif,inactif,en_attente'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = User::query()->select(['id', 'nom', 'prenom', 'email', 'role', 'status', 'created_at', 'auth_version'])
            ->with('admin:id,user_id,admin_status')->orderBy('id');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%$search%")
                    ->orWhere('prenom', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        $result = $query->paginate(10);
        $result->getCollection()->each(function ($user) {
            $user->setAttribute('status_revision', app(AccountStatusRevision::class)->token($user, $user->admin));
            $user->unsetRelation('admin');
        });

        return response()->json($result, 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * Store a newly created admin user.
     */
    public function storeAdmin(StoreAdminRequest $request): JsonResponse
    {
        if (! $this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validated();

        try {
            [$user, $newAdmin] = DB::transaction(function () use ($validated, $request) {
                $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if (! $actor->isApprovedAdmin()) {
                    throw new AuthorizationException('Administrative access is unavailable.');
                }
                if ($actor->auth_version !== $request->user()->auth_version || ! Hash::check($validated['current_password'], $actor->password)) {
                    throw ValidationException::withMessages(['current_password' => 'Confirm your current password before creating an administrator.']);
                }
                $user = User::create([
                    'nom' => $validated['nom'],
                    'prenom' => $validated['prenom'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'telephone' => $validated['telephone'] ?? null,
                    'role' => 'admin',
                    'status' => 'actif',
                ]);

                $newAdmin = AdminModel::create([
                    'user_id' => $user->id,
                    'admin_status' => 1,
                ]);

                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => 'created_admin',
                    'target_type' => 'user',
                    'target_id' => $user->id,
                ]);

                return [$user, $newAdmin];
            }, 3);

            return response()->json([
                'message' => 'Admin user created successfully',
                'user' => $user,
                'admin' => $newAdmin,
            ], 201, ['Cache-Control' => 'no-store']);

        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Admin creation failed', ['exception' => get_class($e)]);

            return response()->json([
                'message' => 'Admin user creation failed',
            ], 500);
        }
    }

    /**
     * Update an admin's status.
     */
    public function updateAdminStatus(UpdateAdminStatusRequest $request, int $id): JsonResponse
    {
        if (! $this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validated();
        if ((int) $validated['admin_status'] === 0 && AdminModel::whereKey($id)->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Another administrator must change your own administrative access.'], 409);
        }

        try {
            [$admin, $changed] = DB::transaction(function () use ($request, $id, $validated) {
                $profile = AdminModel::findOrFail($id);
                $user = $this->lockStatusUsers($request, $profile->user_id);
                $admin = AdminModel::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($admin->user_id !== $user->id || ! $user->isAdmin()) {
                    throw new AuthorizationException('Administrative account is unavailable.');
                }
                app(AccountStatusRevision::class)->assertCurrent($validated['expected_status_revision'], $user, $admin);
                $previousApproval = $admin->admin_status;
                if (in_array($previousApproval, [0, 1, '0', '1'], true) && (int) $previousApproval === (int) $validated['admin_status']) {
                    $admin->setAttribute('status_revision', app(AccountStatusRevision::class)->token($user, $admin));

                    return [$admin, false];
                }
                $admin->update(['admin_status' => $validated['admin_status']]);
                if ((int) $validated['admin_status'] === 0) {
                    app(AccountCredentials::class)->revoke($user);
                }
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'updated_admin_status',
                    'target_type' => 'admin',
                    'target_id' => $id,
                    'metadata' => json_encode([
                        'from' => in_array($previousApproval, [0, 1, '0', '1'], true) ? (int) $previousApproval : $previousApproval,
                        'to' => (int) $validated['admin_status'],
                        'access_revoked' => (int) $validated['admin_status'] === 0,
                        ...isset($validated['reason']) ? ['reason' => $validated['reason']] : [],
                    ], JSON_THROW_ON_ERROR),
                ]);

                $admin->setAttribute('status_revision', app(AccountStatusRevision::class)->token($user, $admin));

                return [$admin, true];
            }, 3);

            return response()->json([
                'message' => $changed ? 'Admin status updated successfully' : 'Admin status is already current',
                'admin' => $admin,
                'changed' => $changed,
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (AuthorizationException|ModelNotFoundException|HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update admin status',
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (! $this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        try {
            $user = User::findOrFail($id);
            $data = ['user' => $user, 'status_revision' => app(AccountStatusRevision::class)->token($user, $user->admin)];

            switch ($user->role) {
                case 'patient':
                    $data['patient'] = Patient::where('user_id', $user->id)
                        ->with('medecinFavori.user')
                        ->first();
                    break;
                case 'medecin':
                    $data['doctor'] = Doctor::where('user_id', $user->id)
                        ->with(['speciality', 'documents'])
                        ->first();
                    break;
                case 'admin':
                    $data['admin'] = AdminModel::where('user_id', $user->id)
                        ->first();
                    break;
            }

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }
    }

    /**
     * Update the specified user's status.
     */
    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        if (! $this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validated();
        if ($id === $request->user()->id && $validated['status'] !== 'actif') {
            return response()->json(['message' => 'Another administrator must deactivate your account.'], 409);
        }

        try {
            [$user, $changed] = DB::transaction(function () use ($request, $id, $validated) {
                $user = $this->lockStatusUsers($request, $id);
                $admin = AdminModel::where('user_id', $user->id)->lockForUpdate()->first();
                app(AccountStatusRevision::class)->assertCurrent($validated['expected_status_revision'], $user, $admin);
                $previousStatus = $user->status;
                if ($previousStatus === $validated['status']) {
                    $user->setAttribute('status_revision', app(AccountStatusRevision::class)->token($user, $admin));

                    return [$user, false];
                }
                $user->status = $validated['status'];
                if ($user->status !== 'actif') {
                    app(AccountCredentials::class)->revoke($user);
                } else {
                    $user->save();
                }
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'updated_user_status',
                    'target_type' => 'user',
                    'target_id' => $user->id,
                    'metadata' => json_encode([
                        'from' => $previousStatus,
                        'to' => $user->status,
                        'access_revoked' => $user->status !== 'actif',
                        ...isset($validated['reason']) ? ['reason' => $validated['reason']] : [],
                    ], JSON_THROW_ON_ERROR),
                ]);

                $user->setAttribute('status_revision', app(AccountStatusRevision::class)->token($user, $admin));

                return [$user, true];
            }, 3);

            return response()->json([
                'message' => $changed ? 'User status updated successfully' : 'User status is already current',
                'user' => $user,
                'changed' => $changed,
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (AuthorizationException|ModelNotFoundException|HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user status',
            ], 500);
        }
    }

    /**
     * Reset user password.
     */
    public function resetPassword(ResetPasswordRequest $request, int $id): JsonResponse
    {
        if (! $this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($request, $id, $validated) {
                // Two administrators resetting each other must acquire user locks in the same order.
                $users = User::whereIn('id', [$request->user()->id, $id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $actor = $users->get($request->user()->id);
                if (! $actor || ! $actor->isApprovedAdmin()) {
                    throw new AuthorizationException('Administrative access is unavailable.');
                }
                if ($actor->auth_version !== $request->user()->auth_version || ! Hash::check($validated['current_password'], $actor->password)) {
                    throw ValidationException::withMessages(['current_password' => 'Confirm your current password before resetting another account.']);
                }
                if (! $users->has($id)) {
                    throw (new ModelNotFoundException)->setModel(User::class, [$id]);
                }
                $user = app(AccountCredentials::class)->changePassword($id, $validated['password']);
                AuditLog::create([
                    'user_id' => $actor->id,
                    'action' => 'reset_user_password',
                    'target_type' => 'user',
                    'target_id' => $user->id,
                ]);
            }, 3);

            return response()->json(['message' => 'Password reset successfully', 'access_revoked' => true], 200, ['Cache-Control' => 'no-store']);

        } catch (ValidationException|AuthorizationException|ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset password',
            ], 500);
        }
    }

    /**
     * Delete the specified user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        User::findOrFail($id);

        // Cascading deletion would discard clinical and billing history and orphan files.
        return response()->json([
            'message' => 'Permanent account deletion is unavailable. Deactivate the account to revoke access while preserving its history.',
            'code' => 'account_deletion_unavailable',
        ], 409, ['Cache-Control' => 'no-store']);
    }

    /**
     * Check if the user is an active admin.
     */
    private function checkAdminPermission(Request $request): bool
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return false;
        }

        $admin = AdminModel::where('user_id', $user->id)->first();

        return $admin && $admin->admin_status == 1;
    }

    // Called inside the status transaction. Approval changes also lock their
    // owner's user row, so both authorization and mutation share this ordering.
    private function lockStatusUsers(Request $request, int $targetId): User
    {
        $users = User::whereIn('id', [$request->user()->id, $targetId])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $actor = $users->get($request->user()->id);
        if (! $actor || ! $actor->isApprovedAdmin()
            || $actor->auth_version !== $request->user()->auth_version) {
            throw new AuthorizationException('Administrative access is unavailable. Please sign in again.');
        }
        if (! $users->has($targetId)) {
            throw (new ModelNotFoundException)->setModel(User::class, [$targetId]);
        }

        return $users->get($targetId);
    }
}
