<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    /**
     * Check if user has permission for a resource action
     */
    public function hasPermission(User $user, string $module, string $resource, string $action): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $permission = "{$module}.{$resource}.{$action}";
        return $user->hasPermissionTo($permission);
    }

    /**
     * Check if user has access to a module
     */
    public function hasModuleAccess(User $user, string $module): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $moduleKey = strtolower($module);
        return $user->getAllPermissions()
            ->pluck('name')
            ->some(fn ($perm) => str_starts_with($perm, "{$moduleKey}."));
    }

    /**
     * Get all modules user has access to
     */
    public function getUserModules(User $user): Collection
    {
        if ($user->hasRole('super-admin')) {
            return collect([
                'core', 'auditlog', 'crm', 'ecommerce', 'pos', 'accounting',
                'billing', 'inventory', 'logistics', 'achats', 'purchasing',
                'manufacturing', 'plm', 'planning', 'quality', 'hr',
                'timesheets', 'helpdesk', 'discussion', 'projects',
                'documents', 'validation', 'email', 'whatsapp',
                'marketingautomation', 'bi', 'workflowautomation'
            ]);
        }

        $permissions = $user->getAllPermissions()->pluck('name');
        $modules = collect();

        foreach ($permissions as $permission) {
            if (preg_match('/^([a-z]+)\./', $permission, $matches)) {
                $modules->push($matches[1]);
            }
        }

        return $modules->unique();
    }

    /**
     * Get all resources user can access in a module
     */
    public function getModuleResources(User $user, string $module): Collection
    {
        $moduleKey = strtolower($module);
        $permissions = $user->getAllPermissions()->pluck('name');
        $resources = collect();

        foreach ($permissions as $permission) {
            if (preg_match("/^{$moduleKey}\.([a-z-_]+)\./", $permission, $matches)) {
                $resources->push($matches[1]);
            }
        }

        return $resources->unique();
    }

    /**
     * Get allowed actions for a resource
     */
    public function getResourceActions(User $user, string $module, string $resource): Collection
    {
        $moduleKey = strtolower($module);
        $permissions = $user->getAllPermissions()->pluck('name');
        $actions = collect();

        foreach ($permissions as $permission) {
            if (preg_match("/^{$moduleKey}\.{$resource}\.([a-z-_]+)$/", $permission, $matches)) {
                $actions->push($matches[1]);
            }
        }

        return $actions;
    }

    /**
     * Create or update a permission
     */
    public function createOrUpdatePermission(string $name, string $guardName = 'web'): Permission
    {
        return Permission::firstOrCreate(
            ['name' => $name, 'guard_name' => $guardName]
        );
    }

    /**
     * Create or update a role
     */
    public function createOrUpdateRole(string $name, string $guardName = 'web'): Role
    {
        return Role::firstOrCreate(
            ['name' => $name, 'guard_name' => $guardName]
        );
    }

    /**
     * Sync role permissions
     */
    public function syncRolePermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);
        return $role;
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(User $user, string $roleName): User
    {
        $user->assignRole($roleName);
        return $user;
    }

    /**
     * Revoke role from user
     */
    public function revokeRoleFromUser(User $user, string $roleName): User
    {
        $user->removeRole($roleName);
        return $user;
    }

    /**
     * Get user's roles
     */
    public function getUserRoles(User $user): Collection
    {
        return $user->roles()->pluck('name');
    }

    /**
     * Check if user has role
     */
    public function hasRole(User $user, string $roleName): bool
    {
        return $user->hasRole($roleName);
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(User $user, array $roleNames): bool
    {
        return $user->hasAnyRole($roleNames);
    }
}
