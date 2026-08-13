<?php

declare(strict_types=1);

namespace Modules\BI\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BiDataSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bi.bidatasource.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('bi.bidatasource.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bi.bidatasource.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('bi.bidatasource.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('bi.bidatasource.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('bi.bidatasource.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('bi.bidatasource.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('bi.bidatasource.archive');
    }
}