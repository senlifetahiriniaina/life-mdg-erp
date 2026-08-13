<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\DemandForecast;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\SeasonalFactor;
use Modules\Inventory\Services\SeasonalDemandService;

/**
 * @group Controllers - Seasonal Factor
 *
 * Manage Seasonal Factor resources.
 */
class SeasonalFactorController extends Controller
{
    public function __construct(private readonly SeasonalDemandService $seasonalService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'product_id' => $request->input('product_id'),
            'category_id' => $request->input('category_id'),
            'period_type' => $request->input('period_type'),
        ];

        $query = SeasonalFactor::query();

        foreach ($filters as $key => $value) {
            if ($value !== null) {
                $query->where($key, $value);
            }
        }

        return response()->json(
            $query->paginate($request->input('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:inventory_products,id|required_if:category_id,null',
            'category_id' => 'nullable|exists:inventory_categories,id|required_if:product_id,null',
            'period_type' => 'required|in:monthly,quarterly,yearly',
            'period_index' => 'required|integer|min:1|max:12',
            'factor' => 'required|numeric|min:0.1|max:10',
            'notes' => 'nullable|string|max:500',
        ]);

        $factor = SeasonalFactor::updateOrCreate(
            [
                'product_id' => $validated['product_id'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'period_type' => $validated['period_type'],
                'period_index' => $validated['period_index'],
            ],
            ['factor' => $validated['factor'], 'notes' => $validated['notes'] ?? null]
        );

        return response()->json($factor, $factor->wasRecentlyCreated ? 201 : 200);
    }

    public function show(SeasonalFactor $seasonalFactor): JsonResponse
    {
        return response()->json($seasonalFactor->load(['product', 'category']));
    }

    public function update(Request $request, SeasonalFactor $seasonalFactor): JsonResponse
    {
        $validated = $request->validate([
            'factor' => 'required|numeric|min:0.1|max:10',
            'notes' => 'nullable|string|max:500',
        ]);

        $seasonalFactor->update($validated);

        return response()->json($seasonalFactor);
    }

    public function destroy(SeasonalFactor $seasonalFactor): JsonResponse
    {
        $seasonalFactor->delete();

        return response()->json(null, 204);
    }

    public function applyToForecasts(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:inventory_products,id',
            'period_type' => 'required|in:monthly,quarterly,yearly',
        ]);

        $productId = $request->input('product_id');
        $periodType = $request->input('period_type');

        $forecasts = DemandForecast::where('product_id', $productId)
            ->where('period_type', $periodType)
            ->where('status', 'pending')
            ->get();

        $applied = 0;

        foreach ($forecasts as $forecast) {
            $adjusted = $this->seasonalService->applySeasonalFactor($forecast);
            if ($adjusted) {
                $applied++;
            }
        }

        return response()->json([
            'message' => "Seasonal factors applied to $applied forecasts",
            'applied_count' => $applied,
        ]);
    }

    public function recalculateStockTargets(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:inventory_products,id',
            'base_stock' => 'required|numeric|min:0',
        ]);

        $product = Product::findOrFail($request->input('product_id'));
        $baseStock = (float) $request->input('base_stock');

        $targets = $this->seasonalService->calculateSeasonalStockTargets($product, $baseStock);

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'base_stock' => $baseStock,
            'seasonal_targets' => $targets,
        ]);
    }

    public function getForecastWithSeasonal(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:inventory_products,id',
            'period_type' => 'required|in:monthly,quarterly,yearly',
        ]);

        $productId = $request->input('product_id');
        $periodType = $request->input('period_type');

        $forecasts = DemandForecast::where('product_id', $productId)
            ->where('period_type', $periodType)
            ->with(['product'])
            ->get()
            ->map(function ($forecast) {
                $adjusted = $this->seasonalService->adjustForecastWithSeasonal($forecast);

                return [
                    'id' => $forecast->id,
                    'period_start' => $forecast->period_start,
                    'period_end' => $forecast->period_end,
                    'base_forecast' => $forecast->forecasted_qty,
                    'seasonal_adjusted' => $adjusted,
                    'seasonal_factor' => $adjusted / ($forecast->forecasted_qty ?: 1),
                ];
            });

        return response()->json([
            'product_id' => $productId,
            'period_type' => $periodType,
            'forecasts' => $forecasts,
        ]);
    }

    public function getCategoryFactors(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
        ]);

        $categoryId = $request->input('category_id');
        $periodType = $request->input('period_type', 'monthly');

        $category = Category::findOrFail($categoryId);
        $factors = SeasonalFactor::where('category_id', $categoryId)
            ->where('period_type', $periodType)
            ->orderBy('period_index')
            ->get();

        return response()->json([
            'category_id' => $categoryId,
            'category_name' => $category->name,
            'period_type' => $periodType,
            'factors' => $factors,
        ]);
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'factors' => 'required|array',
            'factors.*.product_id' => 'nullable|exists:inventory_products,id',
            'factors.*.category_id' => 'nullable|exists:inventory_categories,id',
            'factors.*.period_type' => 'required|in:monthly,quarterly,yearly',
            'factors.*.period_index' => 'required|integer|min:1|max:12',
            'factors.*.factor' => 'required|numeric|min:0.1|max:10',
        ]);

        $imported = 0;
        $errors = [];

        foreach ($request->input('factors') as $index => $factorData) {
            if (! $factorData['product_id'] && ! $factorData['category_id']) {
                $errors[] = "Row $index: Must specify either product_id or category_id";

                continue;
            }

            try {
                SeasonalFactor::create($factorData);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row $index: {$e->getMessage()}";
            }
        }

        return response()->json([
            'imported_count' => $imported,
            'total_count' => count($request->input('factors')),
            'errors' => $errors,
        ]);
    }
}
