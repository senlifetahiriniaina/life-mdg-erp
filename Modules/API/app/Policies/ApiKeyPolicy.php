<?php

declare(strict_types=1);

namespace Modules\API\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier 32.5: dropped the phantom `'api-manager'` role from every
 * `hasAnyRole()` check — confirmed via grep that no `'api-manager'` role
 * has ever been seeded anywhere in `RolesAndPermissionsSeeder` (or any
 * other seeder), so it never denied anything real (this policy already
 * fails closed to admin/super-admin regardless) but was a confusing,
 * unreachable reference — same "role name that doesn't exist in this
 * repo's seeder" bug class already fixed for CspViolationPolicy
 * ('security_manager'/'compliance_officer') and Calendar's policies
 * ('super_admin' typo) elsewhere this session.
 */
class ApiKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->hasRole('super-admin');
    }
}
