<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Only hr-manager, manager, and admin roles may read or mutate payroll records.
 * Employees NEVER see each other's salary data — not even their own via this endpoint
 * (they use /api/v1/hr/me/payslips instead).
 */
class PayrollRecordPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager']);
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager', 'hr-manager']);
    }
}
