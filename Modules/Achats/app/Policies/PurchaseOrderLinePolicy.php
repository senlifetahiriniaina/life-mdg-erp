<?php

declare(strict_types=1);

namespace Modules\Achats\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('achats.purchaseorderline.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('achats.purchaseorderline.view');
    }

    public function create(User $user): bool
    {
        return $user->can('achats.purchaseorderline.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('achats.purchaseorderline.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('achats.purchaseorderline.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('achats.purchaseorderline.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('achats.purchaseorderline.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('achats.purchaseorderline.archive');
    }
}