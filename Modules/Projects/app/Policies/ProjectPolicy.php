<?php

declare(strict_types=1);

namespace Modules\Projects\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.project.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('projects.project.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.project.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('projects.project.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('projects.project.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('projects.project.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('projects.project.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('projects.project.archive');
    }
}