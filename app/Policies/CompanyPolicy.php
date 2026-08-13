<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CompanyPolicy extends BaseErpPolicy
{
    /**
     * Only managers, accounting, and admin can manage company hierarchy.
     * Company structure is critical infrastructure.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'accounting', 'admin']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['manager', 'accounting', 'admin']);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['admin']);
    }

    /**
     * Determine if the user can generate consolidation reports.
     */
    public function generateReport(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['manager', 'accounting', 'admin']);
    }

    /**
     * Determine if the user can record intercompany transactions.
     */
    public function recordTransaction(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'accounting', 'admin']);
    }
}
