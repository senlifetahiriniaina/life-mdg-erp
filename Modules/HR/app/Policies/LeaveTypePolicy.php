<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.leave-type.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.leave-type.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.leave-type.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.leave-type.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.leave-type.delete');
    }
}
