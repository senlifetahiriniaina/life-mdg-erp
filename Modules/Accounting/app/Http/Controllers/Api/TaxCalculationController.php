<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Services\TaxService;

/**
 * @group Accounting - Tax Calculation
 *
 * Batch tax calculation, simulation, and history endpoints.
 */
class TaxCalculationController extends Controller
{
    public function __construct(private TaxService $service) {}

    /** POST /tax/calculate/batch */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'           => 'required|array|min:1',
            'items.*.amount'  => 'required|numeric|min:0',
            'items.*.country' => 'required|string|size:2',
            'items.*.type'    => 'nullable|string',
        ]);

        $results = array_map(fn ($item) => [
            'amount'     => $item['amount'],
            'country'    => $item['country'],
            'tax_amount' => round((float) $item['amount'] * 0.18, 2), // default 18% TVA
            'total'      => round((float) $item['amount'] * 1.18, 2),
        ], $validated['items']);

        return response()->json(['data' => $results, 'count' => count($results)]);
    }

    /** POST /tax/calculate/simulate */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'   => 'required|numeric|min:0',
            'country'  => 'required|string|size:2',
            'scenario' => 'nullable|string',
        ]);

        $taxAmount = round((float) $validated['amount'] * 0.18, 2); // default 18% TVA

        return response()->json([
            'data' => [
                'amount'      => $validated['amount'],
                'country'     => $validated['country'],
                'scenario'    => $validated['scenario'] ?? 'default',
                'tax_amount'  => $taxAmount,
                'total'       => $validated['amount'] + $taxAmount,
                'effective_rate' => $validated['amount'] > 0 ? round($taxAmount / $validated['amount'] * 100, 2) : 0,
            ],
        ]);
    }

    /** GET /tax/calculate/history */
    public function history(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);

        return response()->json([
            'data'    => [],
            'message' => 'Tax calculation history requires AuditLog module integration.',
            'per_page'=> $perPage,
        ]);
    }
}
