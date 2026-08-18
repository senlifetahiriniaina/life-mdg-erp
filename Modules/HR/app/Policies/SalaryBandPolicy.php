<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SalaryBandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.salary-band.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.salary-band.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.salary-band.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.salary-band.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.salary-band.delete');
    }

    public function simulateRaise(User $user, Model $model): bool
    {
        return $user->can('hr.salary-band.update');
    }
}
