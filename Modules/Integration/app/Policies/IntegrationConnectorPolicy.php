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
        return $user->can('integration.connector.view') && $this->ownsTenant($user, $connector);
    }

    public function create(User $user): bool
    {
        return $user->can('integration.connector.create');
    }

    public function update(User $user, IntegrationConnector $connector): bool
    {
        return $user->can('integration.connector.update') && $this->ownsTenant($user, $connector);
    }

    public function delete(User $user, IntegrationConnector $connector): bool
    {
        return $user->can('integration.connector.delete') && $this->ownsTenant($user, $connector);
    }

    /**
     * Chantier 8.6 IDOR fix: view()/update()/delete() previously checked
     * only a flat permission string and ignored $connector entirely — any
     * user holding the right permission, from ANY tenant, could reach ANY
     * connector by id. integration_connectors.tenant_id is a string(36)
     * column (leftover UUID-tenant design, same pattern already fixed for
     * Security's company_id columns this session) while $user->company_id
     * is an int — compared as strings here to avoid a silent type-mismatch
     * false-negative on top of closing the real hole.
     */
    private function ownsTenant(User $user, IntegrationConnector $connector): bool
    {
        // Plain property reads (not `??`/isset()) deliberately — `??` on an
        // Eloquent model triggers __isset() -> offsetExists(), which a
        // Mockery::mock(User::class) with no offsetExists() expectation
        // throws a BadMethodCallException on (see
        // IntegrationConnectorPolicyTest.php's plain-mock fixtures).
        $companyId = $user->company_id;

        return (string) ($companyId === null ? '' : $companyId) === (string) $connector->tenant_id;
    }
}
