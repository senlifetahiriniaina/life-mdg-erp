<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.department.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.department.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.department.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.department.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.department.delete');
    }
}
