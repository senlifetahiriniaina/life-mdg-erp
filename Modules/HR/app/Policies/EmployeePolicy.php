<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Chantier 32.17 (HR deep 14-layer audit): view()/update()/delete() used to
 * be pure permission-string checks with zero per-record ownership check at
 * all — since 'employee' (a role every hr-manager/manager/admin already
 * holds via wildcard/direct grant) satisfies hr.employee.view/.update, any
 * user with that permission could view/edit/delete ANY company's employee
 * by id, confirmed empirically. Added the same sameCompany() int-cast-with-
 * ?? 0-sentinel comparison already established this session (e.g. CRM's
 * ContactPolicy) — a no-op when either side lacks a real company_id
 * (pre-chantier data), real scoping once both carry one.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.employee.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.employee.view') && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.employee.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.employee.update') && $this->sameCompany($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.employee.delete') && $this->sameCompany($user, $model);
    }

    private function sameCompany(User $user, Model $model): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($model->company_id ?? 0));
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('hr.employee.approve') && $this->sameCompany($user, $model);
    }

    public function export(User $user): bool
    {
        return $user->can('hr.employee.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('hr.employee.archive') && $this->sameCompany($user, $model);
    }
}