<?php

declare(strict_types=1);

namespace Modules\Accounting\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.account.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('accounting.account.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.account.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('accounting.account.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('accounting.account.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('accounting.account.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('accounting.account.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('accounting.account.archive');
    }
}