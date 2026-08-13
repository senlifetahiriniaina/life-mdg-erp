<?php

declare(strict_types=1);

namespace Modules\Logistics\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CarrierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('logistics.carrier.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('logistics.carrier.view');
    }

    public function create(User $user): bool
    {
        return $user->can('logistics.carrier.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('logistics.carrier.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('logistics.carrier.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('logistics.carrier.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('logistics.carrier.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('logistics.carrier.archive');
    }
}