<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM Account policy.
 *
 * Accounts are shared team resources: any same-tenant user may view and update them.
 * Deletion stays restricted to admins/managers/owner via the base policy.
 * Cross-tenant access is denied.
 */
class CrmAccountPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'owner_id';

    public function view(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    private function sameTenant(User $user, Model $model): bool
    {
        return (string) ($user->tenant_id ?? '') === (string) ($model->tenant_id ?? '');
    }
}
