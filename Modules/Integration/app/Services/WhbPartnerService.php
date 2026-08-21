<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Integration\Models\WhbConnection;
use Modules\Integration\Models\WhbExchange;
use Modules\Integration\Models\WhbPermission;
use RuntimeException;

/**
 * Manages the full WideHalo Bridge (WHB) connection lifecycle:
 *   - Invite code generation and outbound invite creation
 *   - Accepting a partner's invite code
 *   - Admin approval / rejection / suspension
 *   - Sending business data to a partner (local or remote)
 *   - Inbox management for incoming data
 */
class WhbPartnerService
{
    /**
     * Charset for invite codes — uppercase, no confusing characters (0/O/1/I/L).
     */
    private const INVITE_CHARSET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const INVITE_LENGTH    = 8;
    private const INVITE_TTL_HOURS = 24;

    public function __construct(
        private readonly WhbFederationService     $federation,
        private readonly WhbDataSerializerService $serializer,
    ) {}

    // -------------------------------------------------------------------------
    // Invite code
    // -------------------------------------------------------------------------

    /**
     * Generate a unique 8-character alphanumeric invite code.
     * Avoids visually ambiguous characters: 0, O, 1, I, L.
     */
    public function generateInviteCode(): string
    {
        $charset = self::INVITE_CHARSET;
        $length  = strlen($charset) - 1;

        do {
            $code = '';
            for ($i = 0; $i < self::INVITE_LENGTH; $i++) {
                $code .= $charset[random_int(0, $length)];
            }
        } while (WhbConnection::where('invite_code', $code)->exists());

        return $code;
    }

    // -------------------------------------------------------------------------
    // Create outbound invite
    // -------------------------------------------------------------------------

    /**
     * Create a new outbound connection (we invite them).
     *
     * @param  string              $tenantId  Our tenant identifier
     * @param  array<string,mixed> $data      { connection_type, remote_tenant_id?, remote_server_url?, remote_tenant_name? }
     * @param  int                 $userId    ID of the initiating user
     * @throws RuntimeException
     */
    public function createInvite(string $tenantId, array $data, int $userId): WhbConnection
    {
        $type = $data['connection_type'] ?? 'local';

        if ($type === 'local') {
            // Chantier 19 Lot 3: this validated against `users.tenant_id` —
            // the well-documented phantom column, real DB column, never in
            // User::$fillable, never populated by the real registration
            // flow anywhere in this app (see CLAUDE.md's repeated fixes of
            // this exact bug class across ~15 other modules this session).
            // Since it is always null, this check could never pass for any
            // real caller — every same-server ("local") WHB federation
            // invite has been guaranteed-broken, confirmed empirically via
            // a real HTTP request. The real per-tenant boundary column
            // throughout this app (and the one WhbPartnerController's own
            // resolveTenantId() already resolves remote_tenant_id/
            // local_tenant_id from) is company_id, cast to string.
            $remoteTenantId = $data['remote_tenant_id'] ?? null;
            if (! $remoteTenantId) {
                throw new RuntimeException('remote_tenant_id is required for local connections.');
            }

            $exists = DB::table('users')
                ->where('company_id', $remoteTenantId)
                ->exists();

            if (! $exists) {
                throw new RuntimeException("Tenant '{$remoteTenantId}' not found on this server.");
            }
        }

        if ($type === 'remote') {
            $url = $data['remote_server_url'] ?? '';
            if (! str_starts_with($url, 'https://')) {
                throw new RuntimeException('remote_server_url must use HTTPS.');
            }
        }

        /** @var WhbConnection $connection */
        $connection = WhbConnection::create([
            'local_tenant_id'    => $tenantId,
            'remote_tenant_id'   => $data['remote_tenant_id'] ?? null,
            'remote_server_url'  => $data['remote_server_url'] ?? null,
            'remote_tenant_name' => $data['remote_tenant_name'] ?? null,
            'connection_type'    => $type,
            'status'             => 'pending',
            'invite_code'        => $this->generateInviteCode(),
            'invite_expires_at'  => now()->addHours(self::INVITE_TTL_HOURS),
            'shared_secret'      => encrypt($this->federation->generateSecret()),
            'initiated_by'       => $userId,
        ]);

        // For remote connections, immediately send the invite to the remote server.
        if ($type === 'remote') {
            $this->federation->sendInvite($connection);
        }

        return $connection->fresh(['permissions']);
    }

    // -------------------------------------------------------------------------
    // Accept an incoming invite code
    // -------------------------------------------------------------------------

    /**
     * Accept an incoming invite code from a partner (they gave us a code).
     *
     * This does NOT activate the connection — it moves it to pending approval.
     *
     * @throws RuntimeException
     */
    public function acceptInvite(string $tenantId, string $inviteCode, int $userId): WhbConnection
    {
        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::where('invite_code', strtoupper(trim($inviteCode)))->first();

        if (! $connection) {
            throw new RuntimeException('Invalid invite code.');
        }

        if ($connection->isExpired()) {
            throw new RuntimeException('Invite code has expired.');
        }

        if ($connection->status !== 'pending') {
            throw new RuntimeException('This invite has already been used or is no longer valid.');
        }

        // Guard against self-connection
        if ($connection->local_tenant_id === $tenantId) {
            throw new RuntimeException('Cannot connect to yourself.');
        }

        // Fill in the remote tenant info
        $connection->update([
            'remote_tenant_id' => $tenantId,
            'status'           => 'pending',   // still awaiting admin approval
        ]);

        // For a remote connection, the inviter's own row doesn't know who
        // accepted yet (createInvite() sent the invite before any tenant
        // identity existed on our side) — tell them now. Best-effort: a
        // failed notification must not block our own acceptance, same
        // policy as approveConnection()'s refreshSession() call below.
        if ($connection->connection_type === 'remote') {
            $this->federation->sendAccept($connection->refresh(), $tenantId);
        }

        return $connection->fresh(['permissions']);
    }

    // -------------------------------------------------------------------------
    // Admin approval / rejection / suspension
    // -------------------------------------------------------------------------

    /**
     * Approve a pending connection and configure permissions.
     *
     * @param  array<int|string, array<string, mixed>> $permissions
     *         [['data_type'=>'invoice','can_receive'=>true,'can_send'=>false,'auto_accept'=>false], …]
     * @throws RuntimeException
     */
    public function approveConnection(int $connectionId, int $adminUserId, array $permissions, ?string $tenantId = null): WhbConnection
    {
        // Chantier 32.6: this — and rejectConnection()/suspendConnection()
        // below — has always been findOrFail() with zero tenant filter of
        // its own. Currently safe in practice ONLY because the one real
        // caller, WhbPartnerController, always pre-checks
        // WhbConnection::forTenant($tenantId)->find($id) and 404s before
        // ever reaching here — but that makes the guarantee live entirely
        // in the controller, one accidental bypass away from a real IDOR
        // the moment any other caller (a queued job, an artisan command,
        // a future API version) invokes this service method directly.
        // $tenantId is optional (backward compatible with the one existing
        // caller, which already pre-checks) but enforced whenever supplied.
        /** @var WhbConnection|null $connection */
        $connection = $tenantId !== null
            ? WhbConnection::forTenant($tenantId)->findOrFail($connectionId)
            : WhbConnection::findOrFail($connectionId);

        if ($connection->status !== 'pending') {
            throw new RuntimeException('Connection is not in pending state.');
        }

        DB::transaction(function () use ($connection, $adminUserId, $permissions): void {
            $connection->update([
                'status'      => 'active',
                'approved_by' => $adminUserId,
                'approved_at' => now(),
            ]);

            // Bulk upsert permissions
            foreach ($permissions as $perm) {
                WhbPermission::updateOrCreate(
                    [
                        'connection_id' => $connection->id,
                        'data_type'     => $perm['data_type'],
                    ],
                    [
                        'can_receive' => $perm['can_receive'] ?? false,
                        'can_send'    => $perm['can_send'] ?? false,
                        'auto_accept' => $perm['auto_accept'] ?? false,
                    ]
                );
            }
        });

        // For remote connections, exchange session tokens.
        if ($connection->connection_type === 'remote') {
            try {
                $this->federation->refreshSession($connection->refresh());
            } catch (\Throwable $e) {
                // Log but do not fail the approval
                \Illuminate\Support\Facades\Log::warning('[WHB] Session token exchange failed after approval', [
                    'connection_id' => $connection->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        return $connection->fresh(['permissions', 'exchanges']);
    }

    /**
     * Reject a pending connection.
     */
    public function rejectConnection(int $connectionId, ?string $tenantId = null): void
    {
        $query = $tenantId !== null ? WhbConnection::forTenant($tenantId) : WhbConnection::query();
        $query->findOrFail($connectionId)->update(['status' => 'rejected']);
    }

    /**
     * Suspend an active connection.
     */
    public function suspendConnection(int $connectionId, ?string $tenantId = null): void
    {
        $query = $tenantId !== null ? WhbConnection::forTenant($tenantId) : WhbConnection::query();
        $query->findOrFail($connectionId)->update(['status' => 'suspended']);
    }

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /**
     * Get all connections for a tenant (active and pending).
     */
    public function getConnections(string $tenantId): Collection
    {
        return WhbConnection::forTenant($tenantId)
            ->with(['permissions'])
            ->orderByDesc('created_at')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Send data to partner
    // -------------------------------------------------------------------------

    /**
     * Send a local resource to a connected partner.
     *
     * @throws RuntimeException
     */
    public function sendData(int $connectionId, string $dataType, int $resourceId, string $tenantId): WhbExchange
    {
        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::with('permissions')
            ->where('id', $connectionId)
            ->where('local_tenant_id', $tenantId)
            ->first();

        if (! $connection) {
            throw new RuntimeException('Connection not found.');
        }

        if ($connection->status !== 'active') {
            throw new RuntimeException('Connection is not active.');
        }

        // Check send permission
        /** @var WhbPermission|null $permission */
        $permission = $connection->permissions
            ->firstWhere('data_type', $dataType);

        if (! $permission || ! $permission->can_send) {
            throw new RuntimeException("You do not have permission to send '{$dataType}' to this partner.");
        }

        // Serialize the resource
        $payload = $this->serializer->serialize($dataType, $resourceId, $tenantId);

        // Create our outbound exchange record
        /** @var WhbExchange $outbound */
        $outbound = WhbExchange::create([
            'connection_id'       => $connection->id,
            'direction'           => 'outbound',
            'data_type'           => $dataType,
            'local_resource_type' => $dataType,
            'local_resource_id'   => $resourceId,
            'payload'             => $payload,
            'status'              => 'pending',
            'initiated_by'        => auth()->id(),
        ]);

        if ($connection->connection_type === 'local') {
            // Same server: write an inbound exchange on the partner side directly.
            $this->createLocalInbound($connection, $dataType, $payload, $permission);
            $outbound->update(['status' => 'sent', 'processed_at' => now()]);
        } else {
            // Remote server: push via federation HTTP.
            $pushed = $this->federation->push($connection, $dataType, $payload);
            $outbound->update([
                'status'       => $pushed ? 'sent' : 'error',
                'processed_at' => now(),
                'error_message' => $pushed ? null : 'Remote push failed.',
            ]);
        }

        $connection->touch('last_sync_at');

        return $outbound->fresh();
    }

    // -------------------------------------------------------------------------
    // Inbox management
    // -------------------------------------------------------------------------

    /**
     * List pending incoming exchanges for a tenant (awaiting manual approval).
     */
    public function getPendingIncoming(string $tenantId): Collection
    {
        return WhbExchange::whereHas('connection', fn ($q) => $q->where('local_tenant_id', $tenantId))
            ->where('direction', 'inbound')
            ->where('status', 'received')
            ->with('connection')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Accept an incoming exchange (marks it as accepted).
     *
     * @throws RuntimeException
     */
    public function acceptIncoming(int $exchangeId, string $tenantId): void
    {
        $exchange = $this->findInboundForTenant($exchangeId, $tenantId);

        $exchange->update([
            'status'       => 'accepted',
            'processed_at' => now(),
        ]);
    }

    /**
     * Reject an incoming exchange.
     *
     * @throws RuntimeException
     */
    public function rejectIncoming(int $exchangeId, string $tenantId): void
    {
        $exchange = $this->findInboundForTenant($exchangeId, $tenantId);

        $exchange->update([
            'status'       => 'rejected',
            'processed_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * For a local (same-server) connection, directly write an inbound exchange
     * record to the partner's side.
     */
    private function createLocalInbound(
        WhbConnection  $connection,
        string         $dataType,
        array          $payload,
        WhbPermission  $permission,
    ): void {
        // Find the partner's connection record (mirror record on their side)
        $partnerConnection = WhbConnection::where('local_tenant_id', $connection->remote_tenant_id)
            ->where('remote_tenant_id', $connection->local_tenant_id)
            ->active()
            ->first();

        // If no mirror record exists, use the same connection (single-record model)
        $targetConnectionId = $partnerConnection?->id ?? $connection->id;

        $status = $permission->auto_accept ? 'accepted' : 'received';

        WhbExchange::create([
            'connection_id'       => $targetConnectionId,
            'direction'           => 'inbound',
            'data_type'           => $dataType,
            'local_resource_type' => $dataType,
            'payload'             => $payload,
            'status'              => $status,
            'processed_at'        => $permission->auto_accept ? now() : null,
        ]);
    }

    /**
     * Find an inbound exchange that belongs to the given tenant.
     *
     * @throws RuntimeException
     */
    private function findInboundForTenant(int $exchangeId, string $tenantId): WhbExchange
    {
        /** @var WhbExchange|null $exchange */
        $exchange = WhbExchange::whereHas(
            'connection',
            fn ($q) => $q->where('local_tenant_id', $tenantId)
        )
            ->where('id', $exchangeId)
            ->where('direction', 'inbound')
            ->first();

        if (! $exchange) {
            throw new RuntimeException('Exchange not found or access denied.');
        }

        return $exchange;
    }
}
