<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\TenantExchange;
use Modules\Core\Models\TenantExchangeHistory;

class TenantExchangeService
{
    /**
     * Send a new exchange request from one tenant to another.
     */
    public function sendExchange(
        string $sourceTenantId,
        string $targetTenantSlugOrId,
        string $type,
        array $payload,
        int $userId
    ): TenantExchange {
        $target = $this->findTenantBySlugOrId($targetTenantSlugOrId);

        if ($target === null) {
            throw new \InvalidArgumentException("Target tenant '{$targetTenantSlugOrId}' not found.");
        }

        $targetTenantId = $target->id;

        $exchange = TenantExchange::create([
            'source_tenant_id'  => $sourceTenantId,
            'target_tenant_id'  => $targetTenantId,
            'exchange_type'     => $type,
            'payload'           => $payload,
            'status'            => 'pending',
            'created_by_user_id' => $userId,
        ]);

        $this->addHistory($exchange, 'sent', $sourceTenantId, $userId);

        return $exchange;
    }

    /**
     * Accept a pending exchange request.
     */
    public function accept(TenantExchange $exchange, int $acceptingUserId): void
    {
        if (! $exchange->isPending()) {
            throw new \RuntimeException(
                "Cannot accept an exchange that is not pending (current status: {$exchange->status})."
            );
        }

        $exchange->update([
            'status'              => 'accepted',
            'accepted_at'         => now(),
            'accepted_by_user_id' => $acceptingUserId,
        ]);

        $this->addHistory($exchange, 'accepted', $exchange->target_tenant_id, $acceptingUserId);

        $this->importPayload($exchange);
    }

    /**
     * Reject a pending exchange request.
     */
    public function reject(TenantExchange $exchange, int $userId, ?string $reason = null): void
    {
        if (! $exchange->isPending()) {
            throw new \RuntimeException(
                "Cannot reject an exchange that is not pending (current status: {$exchange->status})."
            );
        }

        $exchange->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $this->addHistory($exchange, 'rejected', $exchange->target_tenant_id, $userId, $reason);
    }

    /**
     * Cancel a pending exchange request (sender only).
     */
    public function cancel(TenantExchange $exchange, int $userId): void
    {
        if (! $exchange->isPending()) {
            throw new \RuntimeException(
                "Cannot cancel an exchange that is not pending (current status: {$exchange->status})."
            );
        }

        $exchange->update(['status' => 'cancelled']);

        $this->addHistory($exchange, 'cancelled', $exchange->source_tenant_id, $userId);
    }

    /**
     * Import the payload into the target tenant's context.
     *
     * For now this logs the import event; actual cross-tenant DB writes
     * depend on a full multi-tenant driver and are handled per-module.
     */
    public function importPayload(TenantExchange $exchange): void
    {
        Log::info('TenantExchangeService: importing payload', [
            'exchange_id'      => $exchange->id,
            'exchange_type'    => $exchange->exchange_type,
            'source_tenant_id' => $exchange->source_tenant_id,
            'target_tenant_id' => $exchange->target_tenant_id,
            'payload_keys'     => array_keys($exchange->payload ?? []),
        ]);
    }

    /**
     * Find a tenant by its slug or string ID.
     */
    public function findTenantBySlugOrId(string $slugOrId): ?object
    {
        return DB::table('tenants')
            ->where('slug', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->first();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function addHistory(
        TenantExchange $exchange,
        string $action,
        string $actorTenantId,
        ?int $actorUserId = null,
        ?string $note = null
    ): void {
        TenantExchangeHistory::create([
            'exchange_id'     => $exchange->id,
            'action'          => $action,
            'actor_tenant_id' => $actorTenantId,
            'actor_user_id'   => $actorUserId,
            'note'            => $note,
        ]);
    }
}
