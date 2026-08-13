<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base ERP policy.
 *
 * Read access is open to all authenticated users within the tenant.
 * Mutations (update/delete) require either ownership of the record OR an admin role.
 */
abstract class BaseErpPolicy
{
    /** Column on the model that holds the owning user's ID, or null for shared resources. */
    protected ?string $ownerColumn = null;

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

    public function update(User $user, Model $model): bool
    {
        return $this->isAdminOrOwner($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->isAdminOrOwner($user, $model);
    }

    protected function isAdminOrOwner(User $user, Model $model): bool
    {
        if ($user->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            return true;
        }

        if ($this->ownerColumn && isset($model->{$this->ownerColumn})) {
            return (int) $model->{$this->ownerColumn} === $user->id;
        }

        // No ownership column = any authenticated user may mutate (shared resource)
        return true;
    }
}
