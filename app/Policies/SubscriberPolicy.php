<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SubscriberPolicy extends BaseErpPolicy
{
    /** No ownership column — subscribers are not user-owned records. */
    protected ?string $ownerColumn = null;

    /** Anyone can add a subscriber. */
    public function create(User $user): bool
    {
        return true;
    }

    /** GDPR: only managers and above may permanently delete subscriber records. */
    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }
}
