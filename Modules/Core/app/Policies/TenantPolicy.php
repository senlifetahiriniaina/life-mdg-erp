<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('core.tenant.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('core.tenant.view');
    }

    public function create(User $user): bool
    {
        return $user->can('core.tenant.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('core.tenant.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('core.tenant.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('core.tenant.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('core.tenant.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('core.tenant.archive');
    }
}