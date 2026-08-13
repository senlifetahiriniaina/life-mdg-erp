<?php

declare(strict_types=1);

namespace Modules\Integration\Policies;

use App\Models\User;
use Modules\Integration\Models\IntegrationConnector;

class IntegrationConnectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('integration.connector.view-any');
    }

    public function view(User $user, IntegrationConnector $connector): bool
    {
        return $user->can('integration.connector.view');
    }

    public function create(User $user): bool
    {
        return $user->can('integration.connector.create');
    }

    public function update(User $user, IntegrationConnector $connector): bool
    {
        return $user->can('integration.connector.update');
    }

    public function delete(User $user, IntegrationConnector $connector): bool
    {
        return $user->can('integration.connector.delete');
    }
}
