<?php

declare(strict_types=1);

namespace Modules\Accounting\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class GLAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.chart-of-account.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('accounting.chart-of-account.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.chart-of-account.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('accounting.chart-of-account.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('accounting.chart-of-account.delete');
    }
}
