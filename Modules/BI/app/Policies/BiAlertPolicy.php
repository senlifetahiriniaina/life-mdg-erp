<?php

declare(strict_types=1);

namespace Modules\BI\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BiAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bi.bialert.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('bi.bialert.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bi.bialert.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('bi.bialert.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('bi.bialert.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('bi.bialert.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('bi.bialert.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('bi.bialert.archive');
    }
}