<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\SourcingBenchmark;
use Modules\Inventory\Services\SourcingBenchmarkService;

class SourcingBenchmarkController extends Controller
{
    use ScopesToCompany;

    public function __construct(private SourcingBenchmarkService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SourcingBenchmark::class);

        $history = $this->service->history(
            $request->integer('product_id') ?: null,
            $request->integer('product_template_id') ?: null,
            $request->user()?->company_id,
        );

        return response()->json([
            'data' => $history,
            'sources' => SourcingBenchmark::SOURCES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SourcingBenchmark::class);

        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:inventory_products,id',
            'product_template_id' => 'nullable|integer|exists:inventory_product_templates,id',
            'material_label' => 'nullable|string|max:255',
            'source' => 'required|string|in:' . implode(',', array_keys(SourcingBenchmark::SOURCES)),
            'source_name_other' => 'nullable|string|max:255|required_if:source,autre',
            'source_url' => 'nullable|url|max:2048',
            'unit_price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'unit' => 'nullable|string|max:20',
            'quantity_reference' => 'nullable|numeric|min:0',
            'observed_at' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $benchmark = $this->service->record($validated, $request->user()?->id, $request->user()?->company_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $benchmark], 201);
    }

    public function destroy(Request $request, SourcingBenchmark $sourcingBenchmark): JsonResponse
    {
        $this->authorize('delete', $sourcingBenchmark);
        $this->assertSameCompany($request, $sourcingBenchmark);

        $this->service->delete($sourcingBenchmark);

        return response()->json(null, 204);
    }

    public function compare(Request $request, Product $product): JsonResponse
    {
        $this->authorize('viewAny', SourcingBenchmark::class);
        $this->assertSameCompany($request, $product);

        return response()->json(['data' => $this->service->compare($product)]);
    }
}
