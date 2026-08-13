<?php

declare(strict_types=1);

namespace Modules\Accounting\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.transaction.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('accounting.transaction.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.transaction.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('accounting.transaction.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('accounting.transaction.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('accounting.transaction.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('accounting.transaction.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('accounting.transaction.archive');
    }
}