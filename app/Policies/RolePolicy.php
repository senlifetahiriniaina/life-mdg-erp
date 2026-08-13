<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('core.role.view-any');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('core.role.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('core.role.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('core.role.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('core.role.delete');
    }

    public function manage(User $user, Role $role): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'security-admin']);
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('core.role.update');
    }
}
