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
 * Manage Tax resources in Accounting module.
 */
class TaxController extends Controller
{
    public function __construct(
        private readonly TaxService $service,
    ) {}

    // ─── Tax Rates CRUD ───────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $rates = TaxRate::latest()->paginate(15);

        return response()->json($rates);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TaxRate::class);

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

    public function active(): JsonResponse
    {
        $rates = $this->service->getActiveTaxRates();

        return response()->json($rates);
    }

    public function show(TaxRate $taxRate): JsonResponse
    {
        return response()->json($taxRate);
    }

    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        $this->authorize('update', $taxRate);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:acc_tax_rates,code,'.$taxRate->id,
            'rate' => 'sometimes|required|numeric|min:0',
            'type' => 'in:vat,sales_tax,withholding,custom',
            'country' => 'nullable|string|size:2',
            'is_active' => 'boolean',
            'is_compound' => 'boolean',
            'applies_to' => 'in:all,goods,services',
        ]);

        $taxRate->update($validated);

        return response()->json($taxRate);
    }

    public function destroy(TaxRate $taxRate): JsonResponse
    {
        $this->authorize('delete', $taxRate);

        $taxRate->delete();

        return response()->json(null, 204);
    }

    public function activate(TaxRate $taxRate): JsonResponse
    {
        $taxRate->activate();

        return response()->json($taxRate->fresh());
    }

    public function deactivate(TaxRate $taxRate): JsonResponse
    {
        $taxRate->deactivate();

        return response()->json($taxRate->fresh());
    }

    // ─── Tax Calculation ──────────────────────────────────────────────────────

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'tax_rate_id' => 'required|exists:acc_tax_rates,id',
        ]);

        $taxRate = TaxRate::findOrFail($validated['tax_rate_id']);
        $taxAmount = $this->service->calculateTax((float) $validated['amount'], $taxRate);

        return response()->json([
            'amount' => (float) $validated['amount'],
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => (float) $validated['amount'] + $taxAmount,
        ]);
    }

    public function calculateCompound(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'rate_ids' => 'required|array|min:1',
            'rate_ids.*' => 'exists:acc_tax_rates,id',
        ]);

        $rates = TaxRate::whereIn('id', $validated['rate_ids'])->get()->all();
        $result = $this->service->calculateCompoundTax((float) $validated['amount'], $rates);

        return response()->json(array_merge(['amount' => (float) $validated['amount']], $result));
    }

    // ─── Tax Entries ──────────────────────────────────────────────────────────

    /** GET /tax/entries */
    public function entries(Request $request): JsonResponse
    {
        $entries = \Modules\Accounting\Models\TaxEntry::with('taxRate')
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('tax_rate_id'), fn ($q, $id) => $q->where('tax_rate_id', $id))
            ->when(
                $request->query('period_start') && $request->query('period_end'),
                fn ($q) => $q->whereDate('period_start', '>=', $request->query('period_start'))
                    ->whereDate('period_end', '<=', $request->query('period_end'))
            )
            ->orderByDesc('period_end')
            ->paginate(25);

        return response()->json($entries);
    }

    public function recordEntry(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'tax_rate_id' => 'required|exists:acc_tax_rates,id',
            'invoice_id' => 'nullable|integer',
            'journal_entry_id' => 'nullable|integer',
            'taxable_amount' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'type' => 'in:collected,paid',
        ]);

        $entry = $this->service->recordTaxEntry($validated);

        return response()->json($entry->load('taxRate'), 201);
    }

    // ─── Tax Reports ──────────────────────────────────────────────────────────

    public function liability(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $result = $this->service->getTaxLiability($validated['period_start'], $validated['period_end']);

        return response()->json($result);
    }

    public function report(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $result = $this->service->getTaxReport($validated['period_start'], $validated['period_end']);

        return response()->json($result);
    }

    public function storeEntries(Request $request): JsonResponse
    {
        // Chantier 13: 'tax_rule_id'/'acc_tax_rules' matched neither
        // TaxEntry::$fillable (the real column is 'tax_rate_id') nor any
        // table that still exists — acc_tax_rules was dropped as dead
        // scaffold in an earlier chantier (see CLAUDE.md). Every real call
        // to this endpoint would have failed validation's exists: check
        // with a SQL "table not found" error.
        $validated = $request->validate([
            'entries' => 'required|array',
            'entries.*.tax_rate_id' => 'required|exists:acc_tax_rates,id',
            'entries.*.amount' => 'required|numeric|min:0',
            'entries.*.period' => 'required|string',
        ]);

        $created = [];
        foreach ($validated['entries'] as $entry) {
            $created[] = \Modules\Accounting\Models\TaxEntry::create(array_merge($entry, [
                'created_by' => auth()->id(),
            ]));
        }

        return response()->json(['data' => $created], 201);
    }
}
