<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('core.approvalworkflow.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('core.approvalworkflow.view');
    }

    public function create(User $user): bool
    {
        return $user->can('core.approvalworkflow.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('core.approvalworkflow.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('core.approvalworkflow.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('core.approvalworkflow.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('core.approvalworkflow.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('core.approvalworkflow.archive');
    }
}