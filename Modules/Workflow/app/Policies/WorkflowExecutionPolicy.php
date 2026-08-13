<?php

declare(strict_types=1);

namespace Modules\Workflow\Policies;

use App\Models\User;
use Modules\Workflow\Models\WorkflowExecution;

class WorkflowExecutionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkflowExecution $workflowExecution): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['workflow-manager', 'admin', 'super-admin'])
            || $user->hasPermissionTo('workflow.execution.create');
    }

    public function retry(User $user, WorkflowExecution $workflowExecution): bool
    {
        return $user->hasAnyRole(['workflow-manager', 'admin', 'super-admin'])
            || $user->hasPermissionTo('workflow.execution.retry');
    }

    public function cancel(User $user, WorkflowExecution $workflowExecution): bool
    {
        return $user->hasAnyRole(['workflow-manager', 'admin', 'super-admin']);
    }

    public function delete(User $user, WorkflowExecution $workflowExecution): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function restore(User $user, WorkflowExecution $workflowExecution): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, WorkflowExecution $workflowExecution): bool
    {
        return $user->hasRole('super-admin');
    }
}
