<?php

declare(strict_types=1);

namespace Modules\Accounting\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.expense.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('accounting.expense.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.expense.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('accounting.expense.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('accounting.expense.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('accounting.expense.approve');
    }
}
