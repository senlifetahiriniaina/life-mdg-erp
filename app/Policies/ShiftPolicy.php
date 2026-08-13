<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ShiftPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'employee']);
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasRole(['admin', 'manager', 'employee']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'employee']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasRole(['admin', 'manager', 'employee']);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasRole(['admin', 'manager']);
    }
}
