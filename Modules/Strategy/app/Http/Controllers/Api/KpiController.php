<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyKpi;
use Modules\Strategy\Services\KpiDataService;

class KpiController extends Controller
{
    public function __construct(private KpiDataService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $kpis = StrategyKpi::forTenant($tenantId)
            ->with('latest')
            ->orderBy('name')
            ->paginate(50);

        return response()->json($kpis);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StrategyKpi::class);

        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string',
            'category'           => 'nullable|string|max:100',
            'source_module'      => 'nullable|string|max:100',
            'source_key'         => 'nullable|string|max:100',
            'source_aggregation' => 'nullable|in:sum,avg,count,last,custom',
            'source_filter'      => 'nullable|array',
            'unit'               => 'nullable|string|max:50',
            'frequency'          => 'nullable|in:realtime,daily,weekly,monthly',
            'target_value'       => 'nullable|numeric',
            'warning_threshold'  => 'nullable|numeric',
            'critical_threshold' => 'nullable|numeric',
            'higher_is_better'   => 'nullable|boolean',
            'is_public'          => 'nullable|boolean',
        ]);

        $tenantId = $this->tenantId($request);
        $validated['tenant_id'] = $tenantId;

        $kpi = StrategyKpi::create($validated);

        return response()->json($kpi, 201);
    }

    /**
     * Chantier 32.27: `Route::apiResource('kpis', ...)` registers
     * `GET kpis/{kpi}` against this method, but it never existed on this
     * controller — a guaranteed fatal "call to undefined method" on every
     * real request to that route, confirmed via reflection (not just
     * reading the routes file). Same bug class already documented
     * repeatedly elsewhere this session (apiResource registering a verb the
     * controller doesn't implement). Built for real rather than restricting
     * the route, since a single-KPI detail view is a legitimate, currently
     * missing need (index()/values() only ever return a list or a value
     * history, never the KPI's own definition fields).
     */
    public function show(int $id): JsonResponse
    {
        $kpi = StrategyKpi::findOrFail($id);

        $this->authorize('view', $kpi);

        return response()->json($kpi);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $kpi = StrategyKpi::findOrFail($id);

        $this->authorize('update', $kpi);

        $validated = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'description'        => 'nullable|string',
            'category'           => 'nullable|string|max:100',
            'target_value'       => 'nullable|numeric',
            'warning_threshold'  => 'nullable|numeric',
            'critical_threshold' => 'nullable|numeric',
            'higher_is_better'   => 'nullable|boolean',
            'is_public'          => 'nullable|boolean',
            'frequency'          => 'nullable|in:realtime,daily,weekly,monthly',
        ]);

        $kpi->update($validated);

        return response()->json($kpi->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $kpi = StrategyKpi::findOrFail($id);

        $this->authorize('delete', $kpi);

        $kpi->delete();

        return response()->json(['message' => 'KPI deleted.']);
    }

    /**
     * Chantier 32.27: had zero authorize() call and zero tenant scoping —
     * any authenticated user of any company could read another company's
     * KPI value history just by guessing its id. Fixed with the same
     * tenant-ownership check as update()/destroy().
     */
    public function values(Request $request, int $id): JsonResponse
    {
        $kpi = StrategyKpi::findOrFail($id);
        $this->authorize('view', $kpi);

        $history = $this->service->getHistory($id);

        return response()->json(['kpi_id' => $id, 'values' => $history]);
    }

    /**
     * Chantier 32.27: had zero authorize() call and zero tenant scoping —
     * any authenticated user of any company could trigger a live refresh
     * (and mutate the value-history table) of another company's KPI by id.
     */
    public function refresh(Request $request, int $id): JsonResponse
    {
        $kpi = StrategyKpi::findOrFail($id);
        $this->authorize('update', $kpi);

        $value = $this->service->fetchLiveValue($kpi);
        $entry = $this->service->recordValue($kpi, $value);

        return response()->json(['kpi_id' => $id, 'value' => $value, 'recorded_at' => $entry->recorded_at]);
    }

    public function sources(): JsonResponse
    {
        return response()->json($this->service->getAvailableSources());
    }

    /**
     * Chantier 10 (Strategy): was $request->user()?->tenant_id ?? 'default' —
     * the phantom users.tenant_id column, never populated for real users, so
     * every tenant silently collapsed into one shared 'default' bucket (a
     * live cross-tenant leak). See StrategyPlanController::tenantId() for the
     * full rationale; fixed to the real company_id boundary column.
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }
}
