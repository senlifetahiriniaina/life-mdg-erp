<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\DemandForecast;
use Modules\Inventory\Models\SeasonalFactor;
use Modules\Inventory\Services\DemandForecastService;

/**
 * @group Controllers - Demand Forecast
 *
 * Manage Demand Forecast resources.
 */
class DemandForecastController extends Controller
{
    use ScopesToCompany;

    public function __construct(private readonly DemandForecastService $service) {}

    public function index(Request $request): JsonResponse
    {
        $forecasts = DemandForecast::with(['product', 'warehouse'])
            ->where('company_id', $this->companyId($request))
            ->when($request->product_id, fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('period_start')
            ->paginate(25);

        return response()->json($forecasts);
    }

    public function show(Request $request, DemandForecast $demandForecast): JsonResponse
    {
        $this->assertSameCompany($request, $demandForecast);

        return response()->json($demandForecast->load(['product', 'warehouse']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:inventory_products,id',
            'warehouse_id' => 'nullable|integer|exists:inventory_warehouses,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'period_type' => 'required|in:monthly,weekly',
            'forecasted_qty' => 'required|numeric|min:0',
            'method' => 'nullable|in:moving_average,exponential_smoothing,seasonal',
            'confidence' => 'nullable|numeric|min:0|max:100',
            'metadata' => 'nullable|array',
        ]);

        // company_id is never trusted from client input — always the
        // authenticated caller's own.
        $forecast = DemandForecast::create($data + [
            'status' => 'draft',
            'company_id' => $this->companyId($request),
        ]);

        return response()->json($forecast->load(['product', 'warehouse']), 201);
    }

    public function update(Request $request, DemandForecast $demandForecast): JsonResponse
    {
        $this->assertSameCompany($request, $demandForecast);

        $data = $request->validate([
            'forecasted_qty' => 'sometimes|numeric|min:0',
            'actual_qty' => 'nullable|numeric|min:0',
            'confidence' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:draft,confirmed,expired',
            'metadata' => 'nullable|array',
        ]);

        $demandForecast->update($data);

        return response()->json($demandForecast->fresh(['product', 'warehouse']));
    }

    public function destroy(Request $request, DemandForecast $demandForecast): JsonResponse
    {
        $this->assertSameCompany($request, $demandForecast);
        $demandForecast->delete();

        return response()->json(null, 204);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:inventory_products,id',
            'warehouse_id' => 'nullable|integer|exists:inventory_warehouses,id',
            'months' => 'nullable|integer|min:1|max:24',
            'method' => 'nullable|in:moving_average,exponential_smoothing,seasonal',
        ]);

        $forecasts = $this->service->generate(
            $data['product_id'],
            $data['warehouse_id'] ?? null,
            $data['months'] ?? 3,
            $data['method'] ?? 'moving_average',
        );

        return response()->json(['forecasts' => $forecasts->values(), 'count' => $forecasts->count()]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:inventory_products,id',
            'warehouse_id' => 'nullable|integer|exists:inventory_warehouses,id',
            'month' => 'required|date_format:Y-m',
        ]);

        $forecast = $this->service->reconcile(
            $data['product_id'],
            $data['warehouse_id'] ?? null,
            Carbon::createFromFormat('Y-m', $data['month']),
        );

        if (! $forecast) {
            return response()->json(['message' => 'No forecast found for this period.'], 404);
        }

        return response()->json($forecast->load(['product', 'warehouse']));
    }

    // ── Seasonal factors ──────────────────────────────────────────────────────

    public function seasonalFactors(Request $request): JsonResponse
    {
        $factors = SeasonalFactor::with(['product', 'category'])
            ->when($request->product_id, fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->orderBy('period_index')
            ->get();

        return response()->json($factors);
    }

    public function storeSeasonalFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'nullable|integer|exists:inventory_products,id',
            'category_id' => 'nullable|integer|exists:inventory_categories,id',
            'period_type' => 'required|in:monthly,weekly',
            'period_index' => 'required|integer|min:1|max:53',
            'factor' => 'required|numeric|min:0.01|max:10',
            'notes' => 'nullable|string|max:255',
        ]);

        $factor = SeasonalFactor::updateOrCreate(
            [
                'product_id' => $data['product_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'period_type' => $data['period_type'],
                'period_index' => $data['period_index'],
            ],
            ['factor' => $data['factor'], 'notes' => $data['notes'] ?? null],
        );

        return response()->json($factor, $factor->wasRecentlyCreated ? 201 : 200);
    }

    public function destroySeasonalFactor(SeasonalFactor $seasonalFactor): JsonResponse
    {
        $seasonalFactor->delete();

        return response()->json(null, 204);
    }
}
