<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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

    public function __construct(protected RFQService $service) {}

    /**
     * Chantier 10: was a literal "// Implementation to follow" stub
     * returning null on every call — GET supplier-quotes never actually
     * listed anything.
     */
    public function index(Request $request)
    {
        $query = SupplierQuote::with(['rfq', 'supplier']);

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

    public function show(SupplierQuote $supplier_quote)
    {
        $this->authorize('view', $supplier_quote);

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

        $supplierId = (int) $request->route('supplier');

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

    public function accept(SupplierQuote $supplier_quote)
    {
        $this->authorize('update', $supplier_quote);

        $this->service->selectWinningQuote($supplier_quote);

        return $supplier_quote->refresh();
    }

    public function reject(SupplierQuote $supplier_quote)
    {
        $this->authorize('update', $supplier_quote);

        $this->service->rejectQuote($supplier_quote, 'Rejected');

        return $supplier_quote->refresh();
    }
}
