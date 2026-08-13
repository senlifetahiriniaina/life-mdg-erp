<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WaTemplatePolicy extends BaseErpPolicy
{
    /** Shared resource — no ownership column. */
    protected ?string $ownerColumn = null;

    /** Only admin/manager may delete templates. */
    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }
}
