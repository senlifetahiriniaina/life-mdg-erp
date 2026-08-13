<?php

declare(strict_types=1);

namespace Modules\Projects\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.task.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('projects.task.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.task.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('projects.task.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('projects.task.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('projects.task.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('projects.task.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('projects.task.archive');
    }
}