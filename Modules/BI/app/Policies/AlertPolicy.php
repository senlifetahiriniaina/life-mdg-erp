<?php

declare(strict_types=1);

namespace Modules\BI\Policies;

use App\Models\User;
use Modules\BI\Models\AlertRule;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bi.alert.view-any');
    }

    public function view(User $user, AlertRule $rule): bool
    {
        return $user->can('bi.alert.view') &&
               $this->belongsToCompany($user, $rule);
    }

    public function create(User $user): bool
    {
        return $user->can('bi.alert.create');
    }

    public function update(User $user, AlertRule $rule): bool
    {
        return $user->can('bi.alert.update') &&
               $this->belongsToCompany($user, $rule);
    }

    public function delete(User $user, AlertRule $rule): bool
    {
        return $user->can('bi.alert.delete') &&
               $this->belongsToCompany($user, $rule);
    }

    public function acknowledge(User $user, AlertRule $rule): bool
    {
        return $user->can('bi.alert.acknowledge') &&
               $this->belongsToCompany($user, $rule);
    }

    public function manageRules(User $user, AlertRule $rule): bool
    {
        return $user->can('bi.alert.manage-rules') &&
               $this->belongsToCompany($user, $rule);
    }

    public function escalate(User $user, AlertRule $rule): bool
    {
        return $user->can('bi.alert.escalate') &&
               $this->belongsToCompany($user, $rule);
    }

    public function viewHistory(User $user, AlertRule $rule): bool
    {
        return $user->can('bi.alert.view-history') &&
               $this->belongsToCompany($user, $rule);
    }

    private function belongsToCompany(User $user, AlertRule $rule): bool
    {
        return $user->company_id === $rule->company_id;
    }
}
