<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\TaxService;

/**
 * @group Accounting
 *
 * Manage Tax Rate resources in Accounting module.
 */
class TaxRateController extends Controller
{
    public function __construct(
        private readonly TaxService $service,
    ) {}

    public function index(): JsonResponse
    {
        $rates = TaxRate::latest()->paginate(15);

        return response()->json([
            'data' => $rates->items(),
            'total' => $rates->total(),
            'per_page' => $rates->perPage(),
            'current_page' => $rates->currentPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:acc_tax_rates,code',
            'rate' => 'required|numeric|min:0',
            'type' => 'in:vat,sales_tax,withholding,custom',
            'country' => 'nullable|string|size:2',
            'is_active' => 'boolean',
            'is_compound' => 'boolean',
            'applies_to' => 'in:all,goods,services',
        ]);

        $taxRate = $this->service->createTaxRate($validated);

        return response()->json($taxRate, 201);
    }

    public function show(TaxRate $taxRate): JsonResponse
    {
        return response()->json($taxRate);
    }

    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:50|unique:acc_tax_rates,code,'.$taxRate->id,
            'rate' => 'numeric|min:0',
            'type' => 'in:vat,sales_tax,withholding,custom',
            'country' => 'nullable|string|size:2',
            'is_active' => 'boolean',
            'is_compound' => 'boolean',
            'applies_to' => 'in:all,goods,services',
        ]);

        $updated = $this->service->updateTaxRate($taxRate, $validated);

        return response()->json($updated->fresh());
    }

    public function destroy(TaxRate $taxRate): JsonResponse
    {
        $this->service->deleteTaxRate($taxRate);

        return response()->json(null, 204);
    }

    public function active(): JsonResponse
    {
        $rates = TaxRate::where('is_active', true)->get();

        return response()->json($rates);
    }

    public function activate(TaxRate $taxRate): JsonResponse
    {
        $taxRate->update(['is_active' => true]);
        return response()->json($taxRate->fresh());
    }

    public function deactivate(TaxRate $taxRate): JsonResponse
    {
        $taxRate->update(['is_active' => false]);
        return response()->json($taxRate->fresh());
    }
}
