<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BudgetPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'created_by';

    /**
     * Only managers and accounting staff can create/manage budgets.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'accounting', 'admin']);
    }
}
