<?php

declare(strict_types=1);

namespace Modules\Payroll\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['hr-manager', 'payroll-manager', 'admin', 'super-admin']);
    }

    public function view(User $user, Model $model): bool
    {
        if ($user->hasAnyRole(['hr-manager', 'payroll-manager', 'admin', 'super-admin'])) {
            return true;
        }

        // Employee can view their own payslip
        return isset($model->employee_id) && (int) $model->employee_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['payroll-manager', 'admin', 'super-admin']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['payroll-manager', 'admin', 'super-admin']);
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['hr-manager', 'payroll-manager', 'admin', 'super-admin']);
    }

    public function export(User $user, Model $model): bool
    {
        if ($user->hasAnyRole(['hr-manager', 'payroll-manager', 'admin', 'super-admin'])) {
            return true;
        }

        return isset($model->employee_id) && (int) $model->employee_id === $user->id;
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
