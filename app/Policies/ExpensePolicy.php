<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ExpensePolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'created_by';

    /**
     * Determine if the user can approve an expense.
     * Only managers, accounting, or admin users can approve.
     */
    public function approve(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['manager', 'accounting', 'admin']);
    }
}
