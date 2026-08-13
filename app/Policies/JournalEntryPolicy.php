<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal entries are accounting records — only accountants, managers, and admins
 * may create or mutate them. Posted entries cannot be modified by anyone except admin.
 */
class JournalEntryPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'created_by';

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'accountant']);
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'accountant']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'accountant']);
    }

    public function update(User $user, Model $model): bool
    {
        // Posted entries are immutable — only admin can force-edit
        if (($model->status ?? null) === 'posted') {
            return $user->hasAnyRole(['super-admin', 'admin']);
        }

        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'accountant']);
    }

    public function delete(User $user, Model $model): bool
    {
        if (($model->status ?? null) === 'posted') {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'accountant']);
    }
}
