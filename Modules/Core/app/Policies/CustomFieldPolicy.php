<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomFieldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('core.customfield.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('core.customfield.view');
    }

    public function create(User $user): bool
    {
        return $user->can('core.customfield.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('core.customfield.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('core.customfield.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('core.customfield.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('core.customfield.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('core.customfield.archive');
    }
}