<?php

declare(strict_types=1);

namespace Modules\Payroll\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['hr-manager', 'payroll-officer', 'admin', 'super-admin']);
    }

    public function view(User $user, Model $model): bool
    {
        // Chantier 32.18 (Payroll deep audit): confirmed empirically via
        // tinker that a payroll-officer/hr-manager/admin of ANY company
        // could view ANY other company's payslip by id through
        // PayrollController::show() — this branch granted full access to
        // every user holding one of these 4 roles with zero check that the
        // payslip actually belongs to their own company. Super-admin
        // already bypasses every Gate check via AppServiceProvider's
        // Gate::before, so it doesn't need (and never needed) this branch
        // at all — the other 3 roles are real, ordinary company-scoped
        // roles that must be scoped to their own tenant just like every
        // other real ownership check in this app.
        if ($user->hasAnyRole(['hr-manager', 'payroll-officer', 'admin'])) {
            return $this->sameCompany($user, $model);
        }

        // Employee can view their own payslip. $model->employee_id is an
        // hr_employees.id, not a users.id — comparing it against $user->id
        // directly (the original bug here, same ID-space mismatch pattern
        // fixed elsewhere in this app's Security/HR policies) meant an
        // employee could never pass this check on their own payslip.
        return isset($model->employee_id) && $user->employee !== null
            && (int) $model->employee_id === (int) $user->employee->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['payroll-officer', 'admin', 'super-admin']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['payroll-officer', 'admin', 'super-admin']);
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['hr-manager', 'payroll-officer', 'admin', 'super-admin']);
    }

    public function export(User $user, Model $model): bool
    {
        // Chantier 32.18: same cross-tenant IDOR fix as view() above — this
        // ability has no controller call site today (no export route
        // exists yet), but it mirrors view()'s exact logic and would carry
        // the identical bug the moment an export endpoint is ever wired to
        // it, so it's fixed here defensively at the same time.
        if ($user->hasAnyRole(['hr-manager', 'payroll-officer', 'admin'])) {
            return $this->sameCompany($user, $model);
        }

        return isset($model->employee_id) && $user->employee !== null
            && (int) $model->employee_id === (int) $user->employee->id;
    }

    /**
     * Chantier 32.18: real same-company ownership check — payslips.tenant_id
     * and users.company_id are both plain integer columns (confirmed via
     * Schema::getColumnType), the real tenant boundary already established
     * for this module (PayrollController::tenantId()), unlike Security's
     * string(36) tenant_id columns which need a cast to compare.
     */
    private function sameCompany(User $user, Model $model): bool
    {
        return isset($model->tenant_id)
            && (int) $model->tenant_id === (int) ($user->company_id ?? -1);
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
