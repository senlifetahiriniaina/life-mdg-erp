<?php

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Achats\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Achats\Http\Resources\PurchaseOrderResource;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Services\BulkPurchaseOrderService;
use Modules\Achats\Services\RFQService;

/**
 * @group Controllers - RFQ
 *
 * Manage RFQ resources.
 */
class RFQController extends Controller
{
    use ScopesToCompany;

    public function __construct(
        protected RFQService $service,
        protected BulkPurchaseOrderService $bulkPoService,
    ) {}

    /**
     * Chantier 10: returned a raw paginator (no `meta` wrapping) — the same
     * bug class already fixed once this session for Warehouses/Index.vue
     * (Chantier 8.3il part 2), just undiscovered on the Achats side until
     * now. RFQs/Index.vue reads `data.meta.{current_page,from,to,total,
     * last_page}` for its pager, which was always `undefined`, so the pager
     * silently never rendered on any RFQ list view.
     */
    public function index(Request $request)
    {
        // Chantier 19: had zero company scoping — any authenticated user
        // could list every other company's RFQs.
        $paginator = RFQ::query()
            ->where('company_id', $this->companyId($request))
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Chantier 10: RFQs/Form.vue submits the whole form (header fields +
     * a `lines` array) as one request body — the same silent-data-loss bug
     * already fixed on PurchaseOrderController::store(). `suppliers` (also
     * submitted by the create form) is deliberately NOT wired here: issuing
     * an RFQ to suppliers is a separate, explicit UI action
     * (RFQs/Show.vue's/Index.vue's own "Issue" button, already calling the
     * real, working `POST rfqs/{rfq}/issue` -> RFQService::issueRFQ()) —
     * auto-issuing on create would be a new business rule this session's
     * "don't invent business logic silently" policy defers rather than
     * guesses at. Documented as a known gap: the create form's supplier
     * picker currently has no effect at all.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'description' => 'nullable|string',
            'required_by_date' => 'required|date',
            'deadline_date' => 'nullable|date',
            'lines' => 'nullable|array',
            'lines.*.product_id' => 'nullable|exists:inventory_products,id',
            'lines.*.description' => 'required_with:lines|string',
            'lines.*.quantity' => 'required_with:lines|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string',
            'lines.*.required_date' => 'nullable|date',
            'lines.*.preferred_supplier_id' => 'nullable|exists:achats_suppliers,id',
            'lines.*.notes' => 'nullable|string',
        ]);
        $lines = $data['lines'] ?? [];
        unset($data['lines']);
        $data['created_by'] = auth()->id();
        $data['company_id'] = $this->companyId($request);

        $rfq = $this->service->createRFQ($data);

        foreach ($lines as $lineData) {
            $this->service->addLineToRFQ($rfq, $lineData);
        }

        return response()->json($rfq->load('lines'), 201);
    }

    public function show(Request $request, RFQ $rfq)
    {
        $this->assertSameCompany($request, $rfq);

        return $rfq->load(['lines', 'quotes.supplier']);
    }

    /**
     * Chantier 10: same silent-lines-drop bug as store() on edit — see
     * PurchaseOrderController::update()'s identical delete-and-recreate
     * fix and its reasoning (no per-line id tracking across edits in the
     * Form.vue this shares its pattern with).
     */
    public function update(Request $request, RFQ $rfq)
    {
        $this->assertSameCompany($request, $rfq);

        $data = $request->validate([
            'description' => 'nullable|string',
            'required_by_date' => 'sometimes|date',
            'deadline_date' => 'nullable|date',
            'lines' => 'nullable|array',
            'lines.*.product_id' => 'nullable|exists:inventory_products,id',
            'lines.*.description' => 'required_with:lines|string',
            'lines.*.quantity' => 'required_with:lines|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string',
            'lines.*.required_date' => 'nullable|date',
            'lines.*.preferred_supplier_id' => 'nullable|exists:achats_suppliers,id',
            'lines.*.notes' => 'nullable|string',
        ]);
        $hasLines = array_key_exists('lines', $data);
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $rfq = $this->service->updateRFQ($rfq, $data);

        if ($hasLines) {
            $rfq->lines()->delete();
            foreach ($lines as $lineData) {
                $this->service->addLineToRFQ($rfq, $lineData);
            }
        }

        return $rfq->load('lines');
    }

    public function issue(Request $request, RFQ $rfq)
    {
        $this->assertSameCompany($request, $rfq);

        $data = $request->validate([
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'exists:achats_suppliers,id',
        ]);

        $this->service->issueRFQ($rfq, $data['supplier_ids']);

        return $rfq->fresh();
    }

    public function closeRfq(Request $request, RFQ $rfq)
    {
        $this->assertSameCompany($request, $rfq);

        $this->service->closeRFQ($rfq);

        return $rfq;
    }

    public function comparison(Request $request, RFQ $rfq)
    {
        $this->assertSameCompany($request, $rfq);

        $comparison = $this->service->getQuoteComparison($rfq);

        return response()->json($comparison);
    }

    /**
     * Chantier 32.13 (layer 9 — activated). Creates one real PurchaseOrder
     * per supplier holding an accepted quote on this RFQ — the missing
     * step after SupplierQuoteController::accept(). See
     * BulkPurchaseOrderService::createPOsFromRFQ()'s own docblock.
     */
    public function createPurchaseOrders(Request $request, RFQ $rfq)
    {
        $this->assertSameCompany($request, $rfq);

        abort_unless($rfq->quotes()->where('status', 'accepted')->exists(), 422, 'Aucun devis accepté sur cette demande de devis.');

        // createPOsFromRFQ() returns a plain Support\Collection (not an
        // Eloquent one), so each PO is refreshed individually rather than
        // via a bulk ->fresh() call, which that class doesn't have.
        $purchaseOrders = $this->bulkPoService->createPOsFromRFQ($rfq, $request->user())
            ->map(fn ($po) => $po->fresh(['lines', 'supplier']));

        return PurchaseOrderResource::collection($purchaseOrders)->response()->setStatusCode(201);
    }
}
