<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Integration\Models\WhbConnection;
use Modules\Integration\Models\WhbExchange;
use Modules\Integration\Services\WhbFederationService;
use Modules\Integration\Services\WhbPartnerService;

/**
 * WHB Partner Management Controller
 *
 * Handles all partner-facing operations for the WideHalo Bridge protocol:
 * connection lifecycle, data exchange, and inbox management.
 *
 * All routes require auth:sanctum middleware.
 * Prefix: /api/v1/whb
 */
class WhbPartnerController extends Controller
{
    public function __construct(
        private readonly WhbPartnerService    $partner,
        private readonly WhbFederationService $federation,
    ) {}

    // -------------------------------------------------------------------------
    // Connection management
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/whb/connections
     *
     * List all connections for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $connections = $this->partner->getConnections($tenantId);

        return response()->json([
            'data' => $connections,
        ]);
    }

    /**
     * POST /api/v1/whb/connections/invite
     *
     * Create an outbound invite (we invite them).
     * Body: { connection_type, remote_tenant_id?, remote_server_url?, remote_tenant_name? }
     */
    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_type'    => ['required', 'in:local,remote'],
            'remote_tenant_id'   => ['nullable', 'string', 'max:255'],
            'remote_server_url'  => ['nullable', 'url', 'max:255'],
            'remote_tenant_name' => ['nullable', 'string', 'max:255'],
        ]);

        $tenantId = $this->resolveTenantId($request);

        try {
            $connection = $this->partner->createInvite(
                $tenantId,
                $validated,
                (int) $request->user()->id
            );

            return response()->json([
                'message'     => 'Invitation créée. Partagez le code de connexion avec votre partenaire.',
                'data'        => $connection,
                'invite_code' => $connection->invite_code,
                'expires_at'  => $connection->invite_expires_at?->toIso8601String(),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/whb/connections/join
     *
     * Accept a partner's invite code (they gave us a code).
     * Body: { invite_code }
     */
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'size:8'],
        ]);

        $tenantId = $this->resolveTenantId($request);

        try {
            $connection = $this->partner->acceptInvite(
                $tenantId,
                $validated['invite_code'],
                (int) $request->user()->id
            );

            return response()->json([
                'message' => 'Demande de connexion envoyée. En attente d\'approbation par l\'administrateur.',
                'data'    => $connection,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/whb/connections/{id}
     *
     * Connection detail with permissions and recent exchanges.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::forTenant($tenantId)
            ->with(['permissions', 'exchanges' => fn ($q) => $q->latest()->limit(20)])
            ->find($id);

        if (! $connection) {
            return response()->json(['error' => 'Connexion introuvable.'], 404);
        }

        return response()->json(['data' => $connection]);
    }

    /**
     * POST /api/v1/whb/connections/{id}/approve
     *
     * (Admin) Approve a pending connection and configure permissions.
     * Body: { permissions: [{ data_type, can_receive, can_send, auto_accept }] }
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'permissions'                  => ['array'],
            'permissions.*.data_type'      => ['required', 'in:invoice,purchase_order,quote,document,catalog,inventory,message,contact'],
            'permissions.*.can_receive'    => ['boolean'],
            'permissions.*.can_send'       => ['boolean'],
            'permissions.*.auto_accept'    => ['boolean'],
        ]);

        $tenantId = $this->resolveTenantId($request);

        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::forTenant($tenantId)->find($id);
        if (! $connection) {
            return response()->json(['error' => 'Connexion introuvable.'], 404);
        }

        try {
            $connection = $this->partner->approveConnection(
                $id,
                (int) $request->user()->id,
                $validated['permissions'] ?? []
            );

            return response()->json([
                'message' => 'Connexion approuvée et activée.',
                'data'    => $connection,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/whb/connections/{id}/reject
     *
     * (Admin) Reject a pending connection.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::forTenant($tenantId)->find($id);
        if (! $connection) {
            return response()->json(['error' => 'Connexion introuvable.'], 404);
        }

        try {
            $this->partner->rejectConnection($id);

            return response()->json(['message' => 'Connexion rejetée.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/whb/connections/{id}/suspend
     *
     * (Admin) Suspend an active connection.
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::forTenant($tenantId)->find($id);
        if (! $connection) {
            return response()->json(['error' => 'Connexion introuvable.'], 404);
        }

        try {
            $this->partner->suspendConnection($id);

            return response()->json(['message' => 'Connexion suspendue.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/whb/connections/{id}/exchanges
     *
     * List exchanges for a specific connection.
     */
    public function exchanges(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::forTenant($tenantId)->find($id);

        if (! $connection) {
            return response()->json(['error' => 'Connexion introuvable.'], 404);
        }

        $exchanges = WhbExchange::where('connection_id', $id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($exchanges);
    }

    // -------------------------------------------------------------------------
    // Data exchange
    // -------------------------------------------------------------------------

    /**
     * POST /api/v1/whb/send
     *
     * Send a local resource to a connected partner.
     * Body: { connection_id, data_type, resource_id }
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => ['required', 'integer'],
            'data_type'     => ['required', 'string', 'in:invoice,purchase_order,quote,document,catalog,inventory,message,contact'],
            'resource_id'   => ['required', 'integer'],
        ]);

        $tenantId = $this->resolveTenantId($request);

        try {
            $exchange = $this->partner->sendData(
                (int) $validated['connection_id'],
                $validated['data_type'],
                (int) $validated['resource_id'],
                $tenantId
            );

            return response()->json([
                'message' => 'Document envoyé avec succès.',
                'data'    => $exchange,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Inbox
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/whb/inbox
     *
     * List all incoming data awaiting approval for the current tenant.
     */
    public function inbox(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $pending = $this->partner->getPendingIncoming($tenantId);

        return response()->json([
            'data'  => $pending,
            'count' => $pending->count(),
        ]);
    }

    /**
     * POST /api/v1/whb/inbox/{exchangeId}/accept
     *
     * Accept an incoming exchange.
     */
    public function acceptIncoming(Request $request, int $exchangeId): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        try {
            $this->partner->acceptIncoming($exchangeId, $tenantId);

            return response()->json(['message' => 'Document accepté.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/whb/inbox/{exchangeId}/reject
     *
     * Reject an incoming exchange.
     */
    public function rejectIncoming(Request $request, int $exchangeId): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        try {
            $this->partner->rejectIncoming($exchangeId, $tenantId);

            return response()->json(['message' => 'Document refusé.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/whb/discover?url=https://remote-server.com
     *
     * Discover a remote WideHalo instance capabilities.
     */
    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
        ]);

        if (! str_starts_with($validated['url'], 'https://')) {
            return response()->json(['error' => 'L\'URL doit utiliser HTTPS.'], 422);
        }

        try {
            $info = $this->federation->discover($validated['url']);

            return response()->json(['data' => $info]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Impossible de contacter le serveur distant : ' . $e->getMessage(),
            ], 502);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the current tenant ID from the authenticated user.
     *
     * Chantier 10: was `$user->tenant_id ?? $user->id` — the phantom
     * tenant_id column, falling through to the user's own id. Every
     * `forTenant($tenantId)` call on this controller (index/store/show/
     * approve/reject/suspend/exchanges — this is the ONE shared helper all
     * of them call) scoped `WhbConnection::local_tenant_id` to a fake
     * per-user "tenant" instead of the real company, meaning admin A could
     * never see/act on a federation-partner connection admin B (same real
     * company) created — a functional collaboration bug, not a
     * cross-tenant leak (no shared/guessable fallback), but the earlier
     * Chantier 8.5-light fix that added forTenant() scoping to approve/
     * reject/suspend never actually closed this because the helper itself
     * was still broken. Fixed to the real tenant boundary, company_id (cast
     * to string — local_tenant_id is a string(36) column, the same leftover
     * UUID-tenant-design pattern already documented for Security/Secrets).
     */
    private function resolveTenantId(Request $request): string
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return (string) ($user->company_id ?? 0);
    }
}
