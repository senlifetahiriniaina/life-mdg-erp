<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BankAccountPolicy extends BaseErpPolicy
{
    /**
     * Only accounting and finance staff can manage bank accounts.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'admin']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'admin']);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['admin']);
    }

    /**
     * Determine if the user can reconcile statements.
     */
    public function reconcile(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['accounting', 'finance', 'admin']);
    }
}
