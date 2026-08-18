<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Employees can view and create their own leave requests.
 * Only managers and hr-managers can approve or delete.
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
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager']);
    }

    // Chantier 8.3: BaseErpPolicy::update()'s ownership fallback compares
    // $model->employee_id (an hr_employees.id) to $user->id (a users.id) —
    // two different ID spaces that never match, so a normal employee could
    // never pass authorize('update', $leaveRequest) on their OWN leave
    // request. Compare against the leave request's employee's real
    // user_id instead.
    public function update(User $user, Model $model): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager'])) {
            return true;
        }

        return (int) $model->employee?->user_id === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager']);
    }
}
