<?php

declare(strict_types=1);

namespace Modules\Workflow\Policies;

use App\Models\User;
use Modules\Workflow\Models\WorkflowDefinition;

class WorkflowDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['workflow-manager', 'admin', 'super-admin'])
            || $user->hasPermissionTo('workflow.definition.create');
    }

    public function update(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasAnyRole(['workflow-manager', 'admin', 'super-admin'])
            || $user->hasPermissionTo('workflow.definition.update');
    }

    public function activate(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasAnyRole(['workflow-manager', 'admin', 'super-admin']);
    }

    public function execute(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasAnyRole(['workflow-manager', 'admin', 'super-admin'])
            || $user->hasPermissionTo('workflow.definition.execute');
    }

    public function delete(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin'])
            || $user->hasPermissionTo('workflow.definition.delete');
    }

    public function restore(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, WorkflowDefinition $workflowDefinition): bool
    {
        return $user->hasRole('super-admin');
    }
}
