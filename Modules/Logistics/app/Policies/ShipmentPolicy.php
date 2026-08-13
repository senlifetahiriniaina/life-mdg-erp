<?php

declare(strict_types=1);

namespace Modules\Logistics\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('logistics.shipment.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('logistics.shipment.view');
    }

    public function create(User $user): bool
    {
        return $user->can('logistics.shipment.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('logistics.shipment.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('logistics.shipment.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('logistics.shipment.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('logistics.shipment.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('logistics.shipment.archive');
    }
}