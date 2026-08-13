<?php

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\Workflow;

class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('crm.workflows.view');
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('crm.workflows.view') &&
               ($workflow->owner_id === $user->id || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('crm.workflows.create');
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('crm.workflows.edit') &&
               ($workflow->owner_id === $user->id || $user->hasRole('admin'));
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('crm.workflows.delete') &&
               ($workflow->owner_id === $user->id || $user->hasRole('admin'));
    }

    public function activate(User $user, Workflow $workflow): bool
    {
        return $this->update($user, $workflow);
    }

    public function deactivate(User $user, Workflow $workflow): bool
    {
        return $this->update($user, $workflow);
    }
}
