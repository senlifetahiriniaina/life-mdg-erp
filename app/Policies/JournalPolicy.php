<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Accounting journals (VTE, ACH, BNQ, etc.) are managed by accountants and admins only.
 */
class JournalPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'accountant']);
    }

    public function view(User $user, \Illuminate\Database\Eloquent\Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'accountant']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'accountant']);
    }
}
