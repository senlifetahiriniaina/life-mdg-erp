<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Http\Requests\StoreKpiRequest;
use Modules\BI\Http\Requests\UpdateKpiRequest;
use Modules\BI\Models\Kpi;

/**
 * @group BI - Kpi
 *
 * Manage KPI definitions and retrieve current values.
 */
class KpiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Kpi::when($request->source_module, fn ($q, $v) => $q->where('source_module', $v))
                ->latest()
                ->paginate(50)
        );
    }

    public function store(StoreKpiRequest $request): JsonResponse
    {
        $kpi = Kpi::create($request->validated());

        return response()->json($kpi, 201);
    }

    public function show(Kpi $kpi): JsonResponse
    {
        $kpi->load(['history' => fn ($q) => $q->orderBy('recorded_at', 'desc')->limit(30)]);

        return response()->json($kpi);
    }

    public function update(UpdateKpiRequest $request, Kpi $kpi): JsonResponse
    {
        $kpi->update($request->validated());

        return response()->json($kpi->fresh());
    }

    public function destroy(Kpi $kpi): JsonResponse
    {
        $kpi->delete();

        return response()->json(null, 204);
    }
}
