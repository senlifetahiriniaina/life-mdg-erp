<?php

declare(strict_types=1);

namespace Modules\BI\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BiAnomalyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bi.bianomaly.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('bi.bianomaly.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bi.bianomaly.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('bi.bianomaly.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('bi.bianomaly.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('bi.bianomaly.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('bi.bianomaly.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('bi.bianomaly.archive');
    }
}