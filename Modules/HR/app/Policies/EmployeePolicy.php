<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.employee.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.employee.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.employee.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.employee.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.employee.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('hr.employee.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('hr.employee.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('hr.employee.archive');
    }
}