<?php

declare(strict_types=1);

namespace Modules\Strategy\Policies;

use App\Models\User;
use Modules\Strategy\Models\Ratio;

class RatioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ratio $ratio): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['strategy-analyst', 'admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.ratio.create');
    }

    public function update(User $user, Ratio $ratio): bool
    {
        return $user->hasAnyRole(['strategy-analyst', 'admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.ratio.update');
    }

    public function delete(User $user, Ratio $ratio): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.ratio.delete');
    }

    public function restore(User $user, Ratio $ratio): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, Ratio $ratio): bool
    {
        return $user->hasRole('super-admin');
    }
}
