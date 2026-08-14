<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagementController extends Controller
{
    private function authorizeAdmin(): void
    {
        $user = request()->user();
        if (! ($user instanceof User) || ! $user->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'Insufficient privileges.');
        }
    }

    public function roles(): JsonResponse
    {
        $this->authorizeAdmin();
        $roles = Role::all()->map(function (Role $role): array {
            return [
                'id'         => $role->id,
                'name'       => $role->name,
                'guard_name' => $role->guard_name,
            ];
        });

        return response()->json(['data' => $roles, 'total' => $roles->count()]);
    }

    public function permissions(): JsonResponse
    {
        $this->authorizeAdmin();
        $permissions = Permission::all()->groupBy(function (Permission $p): string {
            $parts = explode('.', $p->name);
            return $parts[0];
        });

        return response()->json(['data' => $permissions, 'total' => $permissions->count()]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $users = User::with('roles:id,name')
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function userRoles(User $user): JsonResponse
    {
        $this->authorizeAdmin();
        return response()->json([
            'user'        => $user->only(['id', 'name', 'email']),
            'roles'       => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'role_name' => ['required', 'string', 'exists:roles,name'],
        ]);

        static::assignRoleToUser($user, $validated['role_name']);

        return response()->json([
            'message' => "Role '{$validated['role_name']}' assigned to {$user->name}.",
            'roles'   => $user->fresh()->getRoleNames(),
        ]);
    }

    /**
     * Assign a role and write the same audit trail as the HTTP-facing
     * assignRole() endpoint, without an HTTP round-trip — used by
     * Modules\Setup\Services\SetupWizardService so the onboarding wizard's
     * admin step shares this controller's exact role-assignment/audit
     * behavior instead of duplicating it.
     */
    public static function assignRoleToUser(User $user, string $role): void
    {
        $user->assignRole($role);

        AuditLog::record('assign_role', null, User::class, $user->id, [
            'role' => $role,
        ]);
    }

    public function revokeRole(User $user, string $role): JsonResponse
    {
        $this->authorizeAdmin();
        $user->removeRole($role);

        AuditLog::record('revoke_role', null, User::class, $user->id, ['role' => $role]);

        return response()->json([
            'message' => "Role '{$role}' revoked from {$user->name}.",
            'roles'   => $user->fresh()->getRoleNames(),
        ]);
    }

    public function assignPermission(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'permission' => ['required', 'string', 'exists:permissions,name'],
        ]);

        $user->givePermissionTo($validated['permission']);

        AuditLog::record('assign_permission', null, User::class, $user->id, [
            'permission' => $validated['permission'],
        ]);

        return response()->json([
            'message'     => "Permission '{$validated['permission']}' granted to {$user->name}.",
            'permissions' => $user->fresh()->getAllPermissions()->pluck('name'),
        ]);
    }

    public function revokePermission(User $user, string $permission): JsonResponse
    {
        $this->authorizeAdmin();
        $user->revokePermissionTo($permission);

        AuditLog::record('revoke_permission', null, User::class, $user->id, [
            'permission' => $permission,
        ]);

        return response()->json([
            'message'     => "Permission '{$permission}' revoked from {$user->name}.",
            'permissions' => $user->fresh()->getAllPermissions()->pluck('name'),
        ]);
    }
}
