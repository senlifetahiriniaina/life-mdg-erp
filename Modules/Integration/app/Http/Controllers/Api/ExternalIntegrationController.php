<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integration\Models\Integration;
use Modules\Integration\Services\IntegrationManager;

/**
 * Chantier 32.6: `Modules\Integration\Services\IntegrationManager` — the
 * mobile-money (Orange Money/Wave/MTN MoMo/M-Pesa), e-commerce
 * (Shopify/WooCommerce/Jumia) and business-tools (Google Workspace/Zapier)
 * connector registry — was a real, fully-written, tested subsystem with
 * ZERO controller/route anywhere in the app (confirmed via grep: only
 * IntegrationManager's own tests and a code comment referenced it). Unlike
 * `IntegrationController`'s generic webhook connectors, this registry is
 * this app's real, CLAUDE.md-documented Africa First mobile-money feature —
 * activated here rather than left dead, matching this session's
 * established "real, self-contained, no controller consumer yet — a needs-
 * wiring gap, not a delete-it gap" precedent (Chantier 9's pessimistic
 * task-locking case).
 *
 * Prefix: /api/v1/integration/external
 */
class ExternalIntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationManager $manager,
    ) {}

    /**
     * GET /api/v1/integration/external
     *
     * Full registry (9 known integrations) merged with this tenant's
     * current connection status for each.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Integration::class);

        $tenantId = (string) ($request->user()->company_id ?? 0);

        return response()->json(['data' => $this->manager->getAvailable($tenantId)]);
    }

    /**
     * POST /api/v1/integration/external/{key}/connect
     *
     * Body: { credentials: { ... per-integration fields, see the registry's
     * own `credentials` metadata array } }
     */
    public function connect(Request $request, string $key): JsonResponse
    {
        $this->authorize('connect', Integration::class);

        $validated = $request->validate([
            'credentials' => ['required', 'array'],
        ]);

        $tenantId = (string) ($request->user()->company_id ?? 0);

        try {
            $integration = $this->manager->connect($tenantId, $key, $validated['credentials']);

            return response()->json(['data' => $integration], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/v1/integration/external/{key}/disconnect
     */
    public function disconnect(Request $request, string $key): JsonResponse
    {
        $integration = $this->findForTenant($request, $key);

        $this->authorize('disconnect', $integration);

        $this->manager->disconnect((string) $integration->tenant_id, $key);

        return response()->json(['message' => 'Intégration déconnectée.']);
    }

    /**
     * POST /api/v1/integration/external/{key}/test
     */
    public function test(Request $request, string $key): JsonResponse
    {
        $integration = $this->findForTenant($request, $key);

        $this->authorize('test', $integration);

        try {
            $result = $this->manager->testConnection($integration);

            return response()->json([
                'success' => $result,
                'status'  => $integration->fresh()->status,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 501);
        }
    }

    /**
     * POST /api/v1/integration/external/{key}/sync
     *
     * Body: { direction?: 'in'|'out'|'both' }
     */
    public function sync(Request $request, string $key): JsonResponse
    {
        $integration = $this->findForTenant($request, $key);

        $this->authorize('sync', $integration);

        $validated = $request->validate([
            'direction' => ['sometimes', 'in:in,out,both'],
        ]);

        try {
            $result = $this->manager->sync($integration, $validated['direction'] ?? 'both');

            return response()->json(['data' => $result]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 501);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findForTenant(Request $request, string $key): Integration
    {
        $tenantId = (string) ($request->user()->company_id ?? 0);

        /** @var Integration|null $integration */
        $integration = Integration::forTenant($tenantId)
            ->where('integration_key', $key)
            ->first();

        abort_if($integration === null, 404, "No connected integration '{$key}' for this tenant.");

        return $integration;
    }
}
