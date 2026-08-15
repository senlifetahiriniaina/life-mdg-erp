<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Modules\Accounting\Services\CostBenchmarkService;
use Modules\Accounting\Services\CostEngineService;

/**
 * Cost Engine API — CAPEX / OPEX / FINEX / RISKEX
 *
 * All endpoints require auth:sanctum.
 * All monetary amounts returned in XOF (OHADA reporting currency) unless
 * the tenant's default currency differs.
 *
 * Routes (prefix: /api/v1/accounting/costs):
 *   GET  /summary              — total by category + period
 *   GET  /bom/{componentSku}   — costs for a BOM component
 *   GET  /product/{id}         — costs for a product
 *   GET  /project/{id}         — costs for a project
 *   GET  /client/{id}          — costs for a client
 *   POST /entries              — record a cost entry
 *   POST /rollup               — trigger cost rollup computation
 *   GET  /benchmarks           — industry cost structure benchmarks
 *   POST /ai-analyze           — AI cost analysis for entity
 *   GET  /list/{entityType}    — paginated rollup list for entity type
 */
class CostEngineController extends Controller
{
    public function __construct(
        private readonly CostEngineService    $costEngine,
        private readonly CostBenchmarkService $benchmark,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // SUMMARY
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @queryParam period  string YYYY-MM  (default: current month)
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $period   = $request->query('period', Carbon::now()->format('Y-m'));

        return response()->json(
            $this->costEngine->getSummary($tenantId, $period)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // ENTITY COSTS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @queryParam period string YYYY-MM
     */
    public function bomComponent(Request $request, string $componentSku): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $period   = $request->query('period', Carbon::now()->format('Y-m'));

        return response()->json(
            $this->costEngine->getComponentCost($componentSku, $period, $tenantId)
        );
    }

    /**
     * @queryParam period string YYYY-MM
     */
    public function product(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $period   = $request->query('period', Carbon::now()->format('Y-m'));

        return response()->json(
            $this->costEngine->getProductCost($id, $period, $tenantId)
        );
    }

    /**
     * @queryParam period string YYYY-MM
     */
    public function project(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $period   = $request->query('period', Carbon::now()->format('Y-m'));

        return response()->json(
            $this->costEngine->getProjectCost($id, $period, $tenantId)
        );
    }

    /**
     * @queryParam period string YYYY-MM
     */
    public function client(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $period   = $request->query('period', Carbon::now()->format('Y-m'));

        return response()->json(
            $this->costEngine->getClientCost($id, $period, $tenantId)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // LIST (paginated rollups per entity type)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @urlParam entityType string bom_component|product|project|client
     * @queryParam period   string YYYY-MM
     * @queryParam per_page int    (default 20, max 100)
     */
    public function list(Request $request, string $entityType): JsonResponse
    {
        $allowed = ['bom_component', 'product', 'project', 'client'];
        if (! in_array($entityType, $allowed, true)) {
            return response()->json(['error' => "Invalid entity type. Allowed: " . implode(', ', $allowed)], 422);
        }

        $tenantId = $this->resolveTenantId($request);
        $period   = $request->query('period', Carbon::now()->format('Y-m'));
        $perPage  = min((int) $request->query('per_page', 20), 100);

        return response()->json(
            $this->costEngine->getRollupsByEntityType($tenantId, $entityType, $period, $perPage)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // RECORD ENTRY
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Record a new cost entry.
     *
     * @bodyParam category_code   string required CAPEX|OPEX|FINEX|RISKEX
     * @bodyParam amount          number required
     * @bodyParam currency        string required ISO 4217 (e.g. XOF, EUR)
     * @bodyParam allocatable_type string required bom_component|product|project|client
     * @bodyParam allocatable_id  int    required
     * @bodyParam source_module   string optional
     * @bodyParam description     string optional
     * @bodyParam period          string optional YYYY-MM
     * @bodyParam cost_driver     string optional per_unit|per_hour|fixed
     * @bodyParam units           number optional
     */
    public function storeEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_code'    => 'required|in:CAPEX,OPEX,FINEX,RISKEX',
            'amount'           => 'required|numeric|min:0',
            'currency'         => 'required|string|size:3',
            'allocatable_type' => 'required|string',
            'allocatable_id'   => 'required|integer|min:1',
            'source_module'    => 'nullable|string|max:50',
            'source_type'      => 'nullable|string|max:50',
            'source_id'        => 'nullable|integer',
            'description'      => 'nullable|string|max:500',
            'period'           => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'cost_driver'      => 'nullable|string|max:100',
            'units'            => 'nullable|numeric|min:0',
            'is_estimated'     => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $this->resolveTenantId($request);

        $entry = $this->costEngine->recordCost($validated);

        return response()->json($entry, 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ROLLUP
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Trigger cost rollup computation for an entity.
     *
     * @bodyParam entity_type string required bom_component|product|project|client
     * @bodyParam entity_id   int    required
     */
    public function triggerRollup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:bom_component,product,project,client',
            'entity_id'   => 'required|integer|min:1',
        ]);

        $tenantId = $this->resolveTenantId($request);

        $rollup = $this->costEngine->rollupCosts(
            $validated['entity_type'],
            (int) $validated['entity_id'],
            $tenantId
        );

        return response()->json($rollup);
    }

    // ─────────────────────────────────────────────────────────────────────
    // BENCHMARKS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Get industry cost structure benchmarks.
     *
     * @queryParam industry string e.g. textile|construction|manufacturing
     * @queryParam country  string ISO alpha-2 (e.g. SN, CI, NG)
     */
    public function benchmarks(Request $request): JsonResponse
    {
        $industry = $request->query('industry', 'manufacturing');
        $country  = $request->query('country', '*');

        $benchmark = $this->benchmark->getIndustryCostStructure($industry, $country);

        return response()->json([
            'benchmark'  => $benchmark,
            'industries' => $this->benchmark->getAvailableIndustries(),
        ]);
    }

    /**
     * Compare entity's costs to industry benchmark.
     *
     * @queryParam entity_type string required
     * @queryParam entity_id   int    required
     * @queryParam industry    string required
     * @queryParam country     string optional
     */
    public function compareToBenchmark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:bom_component,product,project,client',
            'entity_id'   => 'required|integer|min:1',
            'industry'    => 'required|string',
            'country'     => 'nullable|string|size:2',
        ]);

        $tenantId = $this->resolveTenantId($request);

        return response()->json(
            $this->benchmark->compareToIndustry(
                $validated['entity_type'],
                (int) $validated['entity_id'],
                $validated['industry'],
                $validated['country'] ?? '*',
                $tenantId
            )
        );
    }

    /**
     * Detect cost anomalies for the tenant.
     *
     * @queryParam period string YYYY-MM
     */
    public function detectAnomalies(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $period   = $request->query('period', Carbon::now()->format('Y-m'));

        return response()->json(
            $this->benchmark->detectAnomalies($tenantId, $period)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // AI ANALYSIS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * AI cost analysis for an entity.
     *
     * @bodyParam entity_type string required bom_component|product|project|client
     * @bodyParam entity_id   int    required
     */
    public function aiAnalyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:bom_component,product,project,client',
            'entity_id'   => 'required|integer|min:1',
        ]);

        $tenantId = $this->resolveTenantId($request);

        $analysis = $this->costEngine->aiAnalyzeCosts(
            $validated['entity_type'],
            (int) $validated['entity_id'],
            $tenantId
        );

        return response()->json(['analysis' => $analysis]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function resolveTenantId(Request $request): int
    {
        // Multi-tenant: resolve from user's tenant or query param for super-admin
        $user = $request->user();
        return (int) ($user?->tenant_id ?? $request->query('tenant_id', 1));
    }
}
