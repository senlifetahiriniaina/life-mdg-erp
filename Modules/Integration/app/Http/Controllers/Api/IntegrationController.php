<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Models\SyncLog;
use Modules\Integration\Models\WebhookEndpoint;
use Modules\Integration\Services\IntegrationService;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationService $integrationService
    ) {}

    // ---------------------------------------------------------------------------
    // Connectors
    // ---------------------------------------------------------------------------

    /**
     * GET /api/v1/integration/connectors
     * List all connectors for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IntegrationConnector::class);

        $tenantId = $request->user()->company_id ?? 0;

        $connectors = IntegrationConnector::forTenant($tenantId)
            ->with(['webhookEndpoints'])
            ->paginate(20);

        return response()->json($connectors);
    }

    /**
     * POST /api/v1/integration/connectors
     * Create a new connector.
     *
     * Chantier 19 Lot 3: IntegrationConnectorPolicy::create() was fully
     * written but never actually called here — unlike show/activate/
     * addWebhook/dispatch/logs (fixed in Chantier 8.5-light), this method
     * has no route-bound model to IDOR through, but it also had zero
     * authorize() call of any kind, and the `connectors` route group carries
     * no module:/role: gate either — any authenticated user of any role
     * (e.g. a plain sales-rep with zero integration.* permissions) could
     * create a new integration connector for their own tenant. Fixed with
     * the missing authorize() call, matching index()'s viewAny fix above.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', IntegrationConnector::class);

        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'provider_type' => 'required|in:webhook,oauth2,api_key,basic_auth,custom',
            'config'        => 'nullable|array',
        ]);

        $tenantId = $request->user()->company_id ?? 0;

        $connector = $this->integrationService->createConnector([
            'tenant_id'     => $tenantId,
            'name'          => $validated['name'],
            'provider_type' => $validated['provider_type'],
            'config'        => $validated['config'] ?? null,
            'created_by'    => $request->user()->id,
        ]);

        return response()->json($connector, 201);
    }

    /**
     * GET /api/v1/integration/connectors/{connector}
     * Get connector details.
     */
    public function show(IntegrationConnector $connector): JsonResponse
    {
        $this->authorize('view', $connector);

        return response()->json($connector->load(['webhookEndpoints', 'syncLogs']));
    }

    /**
     * POST /api/v1/integration/connectors/{connector}/activate
     * Activate a connector.
     */
    public function activate(IntegrationConnector $connector): JsonResponse
    {
        $this->authorize('update', $connector);

        $connector = $this->integrationService->activateConnector($connector);

        return response()->json($connector);
    }

    /**
     * POST /api/v1/integration/connectors/{connector}/webhook
     * Add a webhook endpoint to a connector.
     */
    public function addWebhook(Request $request, IntegrationConnector $connector): JsonResponse
    {
        $this->authorize('update', $connector);

        $validated = $request->validate([
            'url'             => 'required|url|max:500',
            'method'          => 'nullable|in:GET,POST,PUT,PATCH,DELETE',
            'headers'         => 'nullable|array',
            'secret_key'      => 'nullable|string|max:100',
            'retry_attempts'  => 'nullable|integer|min:0|max:10',
            'timeout_seconds' => 'nullable|integer|min:1|max:300',
        ]);

        $endpoint = $connector->webhookEndpoints()->create([
            'url'             => $validated['url'],
            'method'          => $validated['method'] ?? 'POST',
            'headers'         => $validated['headers'] ?? null,
            'secret_key'      => $validated['secret_key'] ?? null,
            'retry_attempts'  => $validated['retry_attempts'] ?? 3,
            'timeout_seconds' => $validated['timeout_seconds'] ?? 30,
            'is_active'       => true,
        ]);

        return response()->json($endpoint, 201);
    }

    /**
     * POST /api/v1/integration/connectors/{connector}/dispatch
     * Dispatch a webhook payload.
     */
    public function dispatch(Request $request, IntegrationConnector $connector): JsonResponse
    {
        $this->authorize('update', $connector);

        $validated = $request->validate([
            'payload' => 'required|array',
        ]);

        $syncLog = $this->integrationService->dispatchWebhook($connector, $validated['payload']);

        return response()->json($syncLog, 201);
    }

    /**
     * GET /api/v1/integration/connectors/{connector}/logs
     * List sync logs for a connector.
     */
    public function logs(IntegrationConnector $connector): JsonResponse
    {
        $this->authorize('view', $connector);

        $logs = $connector->syncLogs()->latest()->paginate(50);

        return response()->json($logs);
    }

    /**
     * GET /api/v1/integration/stats
     * Get aggregated stats for the current tenant.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->company_id ?? 0;

        $stats = $this->integrationService->getConnectorStats($tenantId);

        return response()->json($stats);
    }
}
