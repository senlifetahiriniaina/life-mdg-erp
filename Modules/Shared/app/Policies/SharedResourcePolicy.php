<?php

declare(strict_types=1);

namespace Modules\Shared\Policies;

use App\Models\User;

class SharedResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Shared resources are readable by all authenticated users
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('admin.modules.view') || $user->hasAnyRole(['admin', 'super-admin', 'tenant-admin']);
    }

    public function update(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin', 'tenant-admin']);
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('super-admin');
    }
}
