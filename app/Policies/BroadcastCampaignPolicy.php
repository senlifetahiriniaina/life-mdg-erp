<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BroadcastCampaignPolicy extends BaseErpPolicy
{
    /** Creator owns the campaign. */
    protected ?string $ownerColumn = 'created_by';

    /** Only admin/manager may delete campaigns. */
    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }
}
