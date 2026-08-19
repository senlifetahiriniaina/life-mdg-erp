<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\FinancialSimulation;
use Modules\Accounting\Models\FinancialSimulationLine;
use Modules\Accounting\Services\FinancialSimulationService;

class FinancialSimulationController extends Controller
{
    public function __construct(private FinancialSimulationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialSimulation::class);

        $simulations = FinancialSimulation::query()
            ->when($request->user()?->company_id, fn ($q, $companyId) => $q->where('company_id', $companyId))
            ->withCount('lines')
            ->latest()
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return response()->json($simulations);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', FinancialSimulation::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'granularity' => 'required|string|in:week,month',
            'start_date' => 'required|date',
            'horizon_periods' => 'required|integer|min:1|max:104',
            'opening_cash_balance' => 'nullable|numeric|min:0',
        ]);

        $simulation = FinancialSimulation::create([
            ...$validated,
            'company_id' => $request->user()?->company_id,
            'created_by' => $request->user()?->id,
            'status' => 'draft',
        ]);

        return response()->json(['data' => $simulation], 201);
    }

    public function show(FinancialSimulation $financialSimulation): JsonResponse
    {
        $this->authorize('view', $financialSimulation);

        $financialSimulation->load('lines');

        return response()->json(['data' => $financialSimulation]);
    }

    public function update(Request $request, FinancialSimulation $financialSimulation): JsonResponse
    {
        $this->authorize('update', $financialSimulation);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'granularity' => 'sometimes|required|string|in:week,month',
            'start_date' => 'sometimes|required|date',
            'horizon_periods' => 'sometimes|required|integer|min:1|max:104',
            'opening_cash_balance' => 'nullable|numeric|min:0',
            'status' => 'sometimes|required|string|in:draft,active,archived',
        ]);

        $financialSimulation->update($validated);

        return response()->json(['data' => $financialSimulation]);
    }

    public function destroy(FinancialSimulation $financialSimulation): JsonResponse
    {
        $this->authorize('delete', $financialSimulation);

        $financialSimulation->delete();

        return response()->json(null, 204);
    }

    public function project(FinancialSimulation $financialSimulation): JsonResponse
    {
        $this->authorize('view', $financialSimulation);

        return response()->json(['data' => $this->service->project($financialSimulation)]);
    }

    public function storeLine(Request $request, FinancialSimulation $financialSimulation): JsonResponse
    {
        $this->authorize('update', $financialSimulation);

        $validated = $this->validateLine($request);

        $line = $financialSimulation->lines()->create($validated);

        return response()->json(['data' => $line], 201);
    }

    public function updateLine(Request $request, FinancialSimulationLine $line): JsonResponse
    {
        $this->authorize('update', $line->simulation);

        if ($line->status === 'realized') {
            return response()->json(['message' => 'A realized line can no longer be edited.'], 422);
        }

        $validated = $this->validateLine($request, sometimes: true);

        $line->update($validated);

        return response()->json(['data' => $line]);
    }

    public function destroyLine(FinancialSimulationLine $line): JsonResponse
    {
        $this->authorize('update', $line->simulation);

        if ($line->status === 'realized') {
            return response()->json(['message' => 'A realized line cannot be deleted — it is linked to a real order.'], 422);
        }

        $line->delete();

        return response()->json(null, 204);
    }

    public function realizeLine(Request $request, FinancialSimulationLine $line): JsonResponse
    {
        $this->authorize('realize', $line);

        try {
            $result = $this->service->realizeLine(
                $line,
                $request->user()?->company_id ?? 0,
                $request->user()?->id ?? 0,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'line' => $result['line'],
                'journal_entry_id' => $result['journal_entry']->id,
            ],
        ]);
    }

    private function validateLine(Request $request, bool $sometimes = false): array
    {
        $prefix = $sometimes ? 'sometimes|required' : 'required';

        return $request->validate([
            'type' => "{$prefix}|string|in:sale,purchase",
            'product_id' => 'nullable|integer|exists:inventory_products,id',
            'supplier_id' => 'nullable|integer|exists:achats_suppliers,id',
            'contact_id' => 'nullable|integer',
            'label' => 'nullable|string|max:255',
            'quantity' => "{$prefix}|numeric|min:0.0001",
            'unit_price' => 'nullable|numeric|min:0',
            'recurrence' => "{$prefix}|string|in:once,weekly,monthly",
            'start_date' => "{$prefix}|date",
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'growth_rate_percent' => 'nullable|numeric|min:-100|max:1000',
            'counterpart_account_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
        ]);
    }
}
