<?php

declare(strict_types=1);

namespace Modules\Logistics\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DeliveryRoundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('logistics.deliveryround.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('logistics.deliveryround.view');
    }

    public function create(User $user): bool
    {
        return $user->can('logistics.deliveryround.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('logistics.deliveryround.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('logistics.deliveryround.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('logistics.deliveryround.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('logistics.deliveryround.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('logistics.deliveryround.archive');
    }
}