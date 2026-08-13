<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaxRatePolicy extends BaseErpPolicy
{
    /**
     * Only accounting and admin users can manage tax rates.
     * Tax rates are shared across the organization, not owned by individuals.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['accounting', 'admin']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['accounting', 'admin']);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['accounting', 'admin']);
    }
}
