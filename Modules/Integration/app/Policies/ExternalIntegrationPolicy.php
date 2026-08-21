<?php

declare(strict_types=1);

namespace Modules\Integration\Policies;

use App\Models\User;
use Modules\Integration\Models\Integration;

/**
 * Chantier 32.6: RBAC for `Modules\Integration\Services\IntegrationManager`
 * (the mobile-money/e-commerce/business-tools registry — Orange Money, Wave,
 * MTN MoMo, M-Pesa, Shopify, WooCommerce, Jumia, Google Workspace, Zapier)
 * — a real, fully-written subsystem this chantier gave its first-ever
 * controller/route producer. Credentials stored on Integration.credentials
 * are encrypted+hidden, but the connect/disconnect/test/sync actions
 * themselves still need real per-tenant gating: mirrors
 * IntegrationConnectorPolicy's permission-string + ownsTenant() shape,
 * since integrations.tenant_id is the same leftover string(36) column type.
 */
class ExternalIntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('integration.external-integration.view-any');
    }

    public function connect(User $user): bool
    {
        return $user->can('integration.external-integration.create');
    }

    /**
     * Chantier 32.6: gated on the `update` verb, not `delete` — disconnecting
     * doesn't destroy the Integration row (it flips status to
     * 'disconnected' and nulls credentials, reconnectable any time), so it
     * belongs with test()/sync() below rather than requiring the stricter
     * `.delete` permission `employee` deliberately never gets (matching
     * this app's established "employee: view-any+view+create+update, no
     * delete" convention) — an employee should be able to disconnect their
     * own company's mobile-money integration without needing a
     * delete-tier permission.
     */
    public function disconnect(User $user, Integration $integration): bool
    {
        return $user->can('integration.external-integration.update') && $this->ownsTenant($user, $integration);
    }

    public function test(User $user, Integration $integration): bool
    {
        return $user->can('integration.external-integration.update') && $this->ownsTenant($user, $integration);
    }

    public function sync(User $user, Integration $integration): bool
    {
        return $user->can('integration.external-integration.update') && $this->ownsTenant($user, $integration);
    }

    private function ownsTenant(User $user, Integration $integration): bool
    {
        // Plain property read (not `??`) deliberately — see
        // IntegrationConnectorPolicy::ownsTenant()'s identical comment on
        // why a Mockery::mock(User::class) with no offsetExists()
        // expectation throws under `??`.
        $companyId = $user->company_id;

        return (string) ($companyId === null ? '' : $companyId) === (string) $integration->tenant_id;
    }
}
