<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class JobPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.job-position.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.job-position.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.job-position.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.job-position.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.job-position.delete');
    }
}
