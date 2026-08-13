<?php

declare(strict_types=1);

namespace Modules\CRM\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Models\EmailSequence;

class EmailSequencePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'sales-rep']);
    }

    public function update(User $user, Model $model): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            return true;
        }

        /** @var EmailSequence $model */
        return (int) $model->created_by === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            return true;
        }

        /** @var EmailSequence $model */
        return (int) $model->created_by === $user->id;
    }
}
