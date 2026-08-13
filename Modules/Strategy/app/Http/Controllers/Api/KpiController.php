<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Strategy\Models\StrategyKpi;
use Modules\Strategy\Services\KpiDataService;

class KpiController extends Controller
{
    public function __construct(private KpiDataService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id', $request->query('tenant_id', 'default'));

        $kpis = StrategyKpi::forTenant($tenantId)
            ->with('latest')
            ->orderBy('name')
            ->paginate(50);

        return response()->json($kpis);
    }

    public function store(Request $request): JsonResponse
    {
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

        $tenantId = $request->header('X-Tenant-Id', 'default');
        $validated['tenant_id'] = $tenantId;

        $kpi = StrategyKpi::create($validated);

        return response()->json($kpi, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
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

        $kpi = StrategyKpi::findOrFail($id);
        $kpi->update($validated);

        return response()->json($kpi->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        StrategyKpi::findOrFail($id)->delete();

        return response()->json(['message' => 'KPI deleted.']);
    }

    public function values(int $id): JsonResponse
    {
        $history = $this->service->getHistory($id);

        return response()->json(['kpi_id' => $id, 'values' => $history]);
    }

    public function refresh(int $id): JsonResponse
    {
        $kpi   = StrategyKpi::findOrFail($id);
        $value = $this->service->fetchLiveValue($kpi);
        $entry = $this->service->recordValue($kpi, $value);

        return response()->json(['kpi_id' => $id, 'value' => $value, 'recorded_at' => $entry->recorded_at]);
    }

    public function sources(): JsonResponse
    {
        return response()->json($this->service->getAvailableSources());
    }
}
