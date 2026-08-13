<?php

declare(strict_types=1);

namespace Modules\API\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WebhookPolicy
{
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
        return $user->hasAnyRole(['admin', 'super-admin', 'api-manager']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin', 'api-manager']);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin', 'api-manager']);
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->hasRole('super-admin');
    }
}
