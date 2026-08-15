<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\TaxJurisdiction;
use Modules\Accounting\Services\TaxService;

/**
 * @group Accounting - Tax Categories
 *
 * Manage tax categories per jurisdiction (TVA, IS, IRPP, etc.).
 */
class TaxCategoriesController extends Controller
{
    public function __construct(private TaxService $service) {}

    public function index(Request $request): JsonResponse
    {
        $categories = TaxJurisdiction::query()
            ->when($request->country, fn ($q) => $q->where('country_code', $request->country))
            ->paginate(50);

        return response()->json($categories);
    }

    public function show(TaxJurisdiction $taxCategory): JsonResponse
    {
        return response()->json(['data' => $taxCategory]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => 'required|string|size:2',
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:20',
            'rate'         => 'required|numeric|min:0|max:100',
            'is_active'    => 'boolean',
        ]);

        $category = TaxJurisdiction::create($validated);

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, TaxJurisdiction $taxCategory): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'rate'      => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $taxCategory->update($validated);

        return response()->json(['data' => $taxCategory]);
    }

    public function destroy(TaxJurisdiction $taxCategory): JsonResponse
    {
        $taxCategory->delete();

        return response()->json(null, 204);
    }

    /** GET /tax/categories/by-jurisdiction/{jurisdiction} */
    public function byJurisdiction(string $jurisdiction): JsonResponse
    {
        $categories = TaxJurisdiction::where('country_code', $jurisdiction)->get();

        return response()->json(['data' => $categories]);
    }
}
