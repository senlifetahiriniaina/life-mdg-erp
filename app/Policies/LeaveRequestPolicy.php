<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Employees can view and create their own leave requests.
 * Only managers and hr-managers can approve or delete.
 *
 * Chantier 32: LeaveRequest gained a real `company_id` column (HR had zero
 * company/tenant scoping anywhere — see EmployeePolicy's docblock for the
 * full rationale). Every per-record ability below now also requires
 * same-company membership, ADDED alongside the existing owner-id-space fix
 * from Chantier 8.3 rather than replacing it — both checks matter
 * independently: the owner check answers "is this my own leave request",
 * the company check answers "does this even belong to my company" (an
 * hr-manager's broad role bypass must not cross a company boundary either).
 */
class LeaveRequestPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'employee_id';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager'])
            && $this->sameCompany($user, $model);
    }

    // Chantier 8.3: BaseErpPolicy::update()'s ownership fallback compares
    // $model->employee_id (an hr_employees.id) to $user->id (a users.id) —
    // two different ID spaces that never match, so a normal employee could
    // never pass authorize('update', $leaveRequest) on their OWN leave
    // request. Compare against the leave request's employee's real
    // user_id instead.
    public function update(User $user, Model $model): bool
    {
        if (! $this->sameCompany($user, $model)) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager'])) {
            return true;
        }

        return (int) $model->employee?->user_id === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager'])
            && $this->sameCompany($user, $model);
    }

    private function sameCompany(User $user, Model $model): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($model->company_id ?? 0));
    }
}
