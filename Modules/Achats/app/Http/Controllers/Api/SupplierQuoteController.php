<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Achats\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\SupplierQuote;
use Modules\Achats\Services\RFQService;

/**
 * @group Controllers - Supplier Quote
 *
 * Manage Supplier Quote resources.
 */
class SupplierQuoteController extends Controller
{
    use AuthorizesRequests;
    use ScopesToCompany;

    public function __construct(protected RFQService $service) {}

    /**
     * Chantier 10: was a literal "// Implementation to follow" stub
     * returning null on every call — GET supplier-quotes never actually
     * listed anything.
     *
     * Chantier 19: had zero company scoping — any authenticated user could
     * list every other company's quotes.
     */
    public function index(Request $request)
    {
        $query = SupplierQuote::with(['rfq', 'supplier'])
            ->where('company_id', $this->companyId($request));

        if ($request->filled('rfq_id')) {
            $query->where('rfq_id', $request->rfq_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query->latest()->paginate($request->get('per_page', 15));
    }

    public function show(Request $request, SupplierQuote $supplier_quote)
    {
        $this->authorize('view', $supplier_quote);
        $this->assertSameCompany($request, $supplier_quote);

        return $supplier_quote->load(['rfq', 'supplier']);
    }

    /**
     * Chantier 10: was a literal "// Implementation to follow" stub —
     * authorized the call then did nothing at all, so POST
     * rfqs/{rfq}/suppliers/{supplier}/quote silently created no record.
     * Wired onto RFQService::recordSupplierQuote(), the real, already-used
     * (RFQController::issue() calls its sibling create path) method this
     * endpoint was clearly written for.
     */
    public function store(Request $request, RFQ $rfq)
    {
        $this->authorize('create', SupplierQuote::class);
        $this->assertSameCompany($request, $rfq);

        $supplierId = (int) $request->route('supplier');

        // Chantier 32.13 (layer 8, business validation): the route param
        // was never checked against a real supplier of the RFQ's own
        // company at all — a caller could reference any numeric id,
        // including a nonexistent one or another company's real supplier,
        // and a SupplierQuote row would still be created (referencing a
        // foreign supplier_id, though correctly company_id-scoped to this
        // RFQ's own company — not a cross-tenant data leak, but a real
        // data-integrity gap on this module's own supplier/quote linkage).
        \Illuminate\Support\Facades\Validator::make(
            ['supplier' => $supplierId],
            ['supplier' => \Illuminate\Validation\Rule::exists('achats_suppliers', 'id')->where('company_id', $rfq->company_id)]
        )->validate();

        $data = $request->validate([
            'unit_price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
            'delivery_days' => 'nullable|integer|min:0',
            'terms' => 'nullable|string',
            'validity_date' => 'nullable|date',
        ]);

        $quote = $this->service->recordSupplierQuote($rfq, $supplierId, $data);

        return response()->json($quote->load(['rfq', 'supplier']), 201);
    }

    public function accept(Request $request, SupplierQuote $supplier_quote)
    {
        $this->authorize('update', $supplier_quote);
        $this->assertSameCompany($request, $supplier_quote);

        $this->service->selectWinningQuote($supplier_quote);

        return $supplier_quote->refresh();
    }

    public function reject(Request $request, SupplierQuote $supplier_quote)
    {
        $this->authorize('update', $supplier_quote);
        $this->assertSameCompany($request, $supplier_quote);

        $this->service->rejectQuote($supplier_quote, 'Rejected');

        return $supplier_quote->refresh();
    }
}
