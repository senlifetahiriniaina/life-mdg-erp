<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier 32: same-company gating on top of the existing permission
 * check — see EmployeePolicy's docblock for the full rationale.
 */
class SalaryBandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.salary-band.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.salary-band.view') && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.salary-band.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.salary-band.update') && $this->sameCompany($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.salary-band.delete') && $this->sameCompany($user, $model);
    }

    public function simulateRaise(User $user, Model $model): bool
    {
        return $user->can('hr.salary-band.update') && $this->sameCompany($user, $model);
    }

    private function sameCompany(User $user, Model $model): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($model->company_id ?? 0));
    }
}
