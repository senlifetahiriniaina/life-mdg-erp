<?php

declare(strict_types=1);

namespace Modules\Accounting\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class JournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting.journal.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('accounting.journal.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.journal.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('accounting.journal.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('accounting.journal.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('accounting.journal.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('accounting.journal.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('accounting.journal.archive');
    }
}