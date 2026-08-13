<?php

declare(strict_types=1);

namespace Modules\Projects\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MilestonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.milestone.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('projects.milestone.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.milestone.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('projects.milestone.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('projects.milestone.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('projects.milestone.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('projects.milestone.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('projects.milestone.archive');
    }
}