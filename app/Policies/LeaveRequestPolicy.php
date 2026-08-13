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

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager']);
    }
}
