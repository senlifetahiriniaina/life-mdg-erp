<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier 32.17 (HR deep 14-layer audit): same sameCompany() retrofit as
 * EmployeePolicy — see that class's docblock for the full rationale.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.department.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.department.view') && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.department.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.department.update') && $this->sameCompany($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.department.delete') && $this->sameCompany($user, $model);
    }

    private function sameCompany(User $user, Model $model): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($model->company_id ?? 0));
    }
}
