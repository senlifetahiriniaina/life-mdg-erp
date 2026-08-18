<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SkillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.skill.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('hr.skill.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hr.skill.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('hr.skill.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('hr.skill.delete');
    }
}
