<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Achats\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Achats\Http\Resources\PurchaseReceiptResource;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Models\PurchaseReceiptLine;
use Modules\Achats\Services\PurchaseReceiptService;

/**
 * @group Controllers - Purchase Receipt
 *
 * Manage Purchase Receipt resources.
 *
 * Chantier 10: rebuilt end-to-end. index()/store()/recordQualityIssue()
 * were literal "// Implementation to follow" stubs, and update()/destroy()
 * were routed against methods that didn't exist anywhere on this class at
 * all (a fatal "call to undefined method" on every PUT/DELETE) — real,
 * already-built frontend pages (PurchaseReceipts/Index+Form+Show.vue) call
 * all of this and were fully broken. No PurchaseReceiptPolicy exists in
 * this module (matches the pre-existing design here — complete() never
 * called authorize() either); this controller stays route-gated-only, the
 * same "no natural policy" precedent already used elsewhere in this app
 * (Security's RateLimitController, Analytics' ForecastingController).
 */
class PurchaseReceiptController extends Controller
{
    use ScopesToCompany;

    public function __construct(protected PurchaseReceiptService $service) {}

    /**
     * GET /purchase-receipts — paginated list, matching
     * PurchaseReceipts/Index.vue's status/has_issues filters and its
     * data/meta pagination expectations.
     *
     * Chantier 19: had zero company scoping — any authenticated user could
     * list every other company's receipts.
     */
    public function index(Request $request)
    {
        $query = PurchaseReceipt::with(['purchaseOrder.supplier', 'lines'])
            ->where('company_id', $this->companyId($request));

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('po_number', 'like', "%{$search}%"));
            });
        }

        $receipts = $query->latest()->paginate($request->get('per_page', 15));

        // has_issues is computed per-line rather than a real column, so it
        // filters the already-fetched page rather than the query — the
        // paginated total below still reflects the unfiltered set, matching
        // how every other filter on this endpoint is a plain WHERE clause;
        // acceptable since PurchaseReceipts/Index.vue treats it as an
        // optional narrowing filter, not a hard requirement.
        if ($request->filled('has_issues')) {
            $wantsIssues = $request->boolean('has_issues');
            $receipts->setCollection(
                $receipts->getCollection()->filter(
                    fn (PurchaseReceipt $r) => $r->hasQualityIssueLines() === $wantsIssues
                )->values()
            );
        }

        return PurchaseReceiptResource::collection($receipts);
    }

    /**
     * POST /purchase-receipts — the plain, non-route-bound-PO variant
     * PurchaseReceipts/Form.vue actually calls (purchase_order_id travels
     * in the body, not the URL — the older `POST
     * purchase-orders/{po}/receipts` route below still exists and now
     * delegates here too). Wired onto the real, previously-unused
     * PurchaseReceiptService::createReceipt()/addReceiptLine()/
     * markLineAsReceived()/recordQualityIssue().
     *
     * Documented gap, not built: the frontend's `quality_issues[]` implies
     * a separate quality-issue entity with its own status workflow
     * (open/investigating/resolved) — no such entity exists anywhere in
     * the real data model (recordQualityIssue() only ever mutates the same
     * line's quality_status+notes). Submitted quality_issues are recorded
     * against their matching receipt line via the real mechanism; the
     * response never populates a `quality_issues[]` list with a status
     * workflow, so Show.vue's dedicated "Quality Issues Reported" section
     * simply never renders — a graceful, fallback-first degradation, not a
     * silent invention of new business rules.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:achats_purchase_orders,id',
            'receipt_date' => 'nullable|date',
            'warehouse_location' => 'nullable|string',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.po_line_id' => 'required_with:lines|exists:achats_purchase_order_lines,id',
            'lines.*.quantity_received' => 'required_with:lines|numeric|min:0',
            'lines.*.quality_status' => 'nullable|string',
            'quality_issues' => 'nullable|array',
            'quality_issues.*.po_line_id' => 'nullable|exists:achats_purchase_order_lines,id',
            'quality_issues.*.issue_type' => 'nullable|string',
            'quality_issues.*.description' => 'nullable|string',
        ]);

        $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);
        // Chantier 19: without this, a user could record a receipt against
        // another company's purchase order by id.
        $this->assertSameCompany($request, $po);

        $receipt = $this->service->createReceipt($po, [
            'receipt_date' => $validated['receipt_date'] ?? now()->toDateString(),
            'received_by' => auth()->id(),
            'warehouse_location' => $validated['warehouse_location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            // addReceiptLine()/completeReceipt() gate on this specific
            // string — the migrated column's own DB default ('pending')
            // doesn't match, so it must be set explicitly here.
            'status' => 'draft',
            'company_id' => $this->companyId($request),
        ]);

        $this->applyLines($receipt, $validated['lines'] ?? [], $validated['quality_issues'] ?? []);

        return response()->json(
            new PurchaseReceiptResource($receipt->load(['purchaseOrder.supplier', 'lines.purchaseOrderLine'])),
            201
        );
    }

    /**
     * POST /purchase-orders/{purchase_order}/receipts — the route-bound-PO
     * variant. Delegates to store()'s real logic via a merged request
     * rather than duplicating it.
     */
    public function storeForOrder(Request $request, PurchaseOrder $purchase_order)
    {
        $this->assertSameCompany($request, $purchase_order);

        $request->merge(['purchase_order_id' => $purchase_order->id]);

        return $this->store($request);
    }

    public function show(Request $request, PurchaseReceipt $purchase_receipt)
    {
        $this->assertSameCompany($request, $purchase_receipt);

        return new PurchaseReceiptResource(
            $purchase_receipt->load(['purchaseOrder.supplier', 'lines.purchaseOrderLine'])
        );
    }

    /**
     * PUT /purchase-receipts/{purchase_receipt} — was routed against a
     * method that didn't exist on this class at all (fatal error on every
     * request). Header fields update directly; `lines`, when present, are
     * replaced wholesale (no per-line id tracking in Form.vue across
     * edits — same delete-and-recreate pattern used for
     * PurchaseOrderController::update()/RFQController::update()), guarded
     * to a receipt still in 'draft' status, matching
     * PurchaseReceiptService::addReceiptLine()'s own existing rule.
     */
    public function update(Request $request, PurchaseReceipt $purchase_receipt)
    {
        $this->assertSameCompany($request, $purchase_receipt);

        $validated = $request->validate([
            'receipt_date' => 'nullable|date',
            'warehouse_location' => 'nullable|string',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.po_line_id' => 'required_with:lines|exists:achats_purchase_order_lines,id',
            'lines.*.quantity_received' => 'required_with:lines|numeric|min:0',
            'lines.*.quality_status' => 'nullable|string',
            'quality_issues' => 'nullable|array',
            'quality_issues.*.po_line_id' => 'nullable|exists:achats_purchase_order_lines,id',
            'quality_issues.*.issue_type' => 'nullable|string',
            'quality_issues.*.description' => 'nullable|string',
        ]);

        $hasLines = array_key_exists('lines', $validated);
        $lines = $validated['lines'] ?? [];
        $qualityIssues = $validated['quality_issues'] ?? [];
        unset($validated['lines'], $validated['quality_issues']);

        $purchase_receipt->update($validated);

        if ($hasLines) {
            abort_if($purchase_receipt->status !== 'draft', 422, 'Cannot edit lines on a non-draft receipt');
            $purchase_receipt->lines()->delete();
            $this->applyLines($purchase_receipt, $lines, $qualityIssues);
        }

        return new PurchaseReceiptResource(
            $purchase_receipt->load(['purchaseOrder.supplier', 'lines.purchaseOrderLine'])
        );
    }

    /**
     * DELETE /purchase-receipts/{purchase_receipt} — was routed against a
     * method that didn't exist on this class at all. Soft-delete (the
     * model already uses SoftDeletes).
     */
    public function destroy(Request $request, PurchaseReceipt $purchase_receipt)
    {
        $this->assertSameCompany($request, $purchase_receipt);

        $purchase_receipt->delete();

        return response()->noContent();
    }

    public function complete(Request $request, PurchaseReceipt $purchase_receipt)
    {
        $this->assertSameCompany($request, $purchase_receipt);

        $this->service->completeReceipt($purchase_receipt);

        return new PurchaseReceiptResource($purchase_receipt->refresh()->load(['purchaseOrder.supplier', 'lines.purchaseOrderLine']));
    }

    /**
     * POST /purchase-receipts/{purchase_receipt}/quality-issue — was a
     * literal stub. Wired onto the real per-line
     * PurchaseReceiptService::recordQualityIssue() (line_id/issue_type/
     * description already match this method's own real parameters).
     */
    public function recordQualityIssue(Request $request, PurchaseReceipt $purchase_receipt)
    {
        $this->assertSameCompany($request, $purchase_receipt);

        $validated = $request->validate([
            'line_id' => 'required|exists:achats_purchase_receipt_lines,id',
            'issue_type' => 'required|string',
            'description' => 'required|string',
        ]);

        $line = $purchase_receipt->lines()->findOrFail($validated['line_id']);

        $this->service->recordQualityIssue($line, $validated['issue_type'], $validated['description']);

        return new PurchaseReceiptResource($purchase_receipt->refresh()->load(['purchaseOrder.supplier', 'lines.purchaseOrderLine']));
    }

    /**
     * Shared line-application logic for store()/update(): adds each
     * receipt line via the real service, marks it received (quantity +
     * quality status), then records any submitted quality issues against
     * their matching line.
     */
    private function applyLines(PurchaseReceipt $receipt, array $lines, array $qualityIssues): void
    {
        foreach ($lines as $lineData) {
            $poLine = PurchaseOrderLine::find($lineData['po_line_id']);

            $line = $this->service->addReceiptLine($receipt, [
                'purchase_order_line_id' => $lineData['po_line_id'],
                'product_id' => $poLine?->product_id,
                'quantity_received' => 0,
                'quality_status' => 'pending',
                'variance_qty' => 0,
            ]);

            $this->service->markLineAsReceived(
                $line,
                (float) $lineData['quantity_received'],
                $lineData['quality_status'] ?? 'good'
            );
        }

        foreach ($qualityIssues as $issue) {
            if (empty($issue['po_line_id'])) {
                continue;
            }

            $line = $receipt->lines()->where('purchase_order_line_id', $issue['po_line_id'])->first();

            if ($line instanceof PurchaseReceiptLine) {
                $this->service->recordQualityIssue($line, $issue['issue_type'] ?? 'other', $issue['description'] ?? '');
            }
        }
    }
}
