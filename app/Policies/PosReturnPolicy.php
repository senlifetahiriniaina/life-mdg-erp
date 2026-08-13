<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PosReturnPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null;

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'employee']);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }
}
