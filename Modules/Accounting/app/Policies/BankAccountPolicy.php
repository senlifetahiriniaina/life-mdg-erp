<?php

declare(strict_types=1);

namespace Modules\Accounting\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.bank-account.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('accounting.bank-account.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.bank-account.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('accounting.bank-account.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('accounting.bank-account.delete');
    }
}
