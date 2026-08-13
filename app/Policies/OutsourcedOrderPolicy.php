<?php
declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

class OutsourcedOrderPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null;

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }
}
