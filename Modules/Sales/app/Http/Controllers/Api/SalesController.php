<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesQuotation;
use Modules\Sales\Services\SalesDepositService;
use Modules\Sales\Services\SalesService;

/**
 * @group Sales
 *
 * Manage sales orders and quotations.
 */
class SalesController extends Controller
{
    public function __construct(
        private readonly SalesService $service,
        private readonly SalesDepositService $depositService,
    ) {}

    /**
     * Chantier 8 (Sales) tenant leak fix: users.tenant_id is a phantom
     * column, never populated by any real registration/onboarding path in
     * this app (same finding already fixed for Setup/Reporting/Strategy
     * this session) — every order/quotation was silently written with
     * tenant_id = 1 and every listing defaulted to reading tenant 1's
     * shared bucket. company_id is the real multi-tenant boundary column
     * (see App\Http\Middleware\InitializeTenancyFromAuthenticatedUser).
     * The SalesOrder/SalesQuotation models' own 'tenant_id' column name is
     * unchanged — only the value written into it changes.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }

    // ─── Orders ────────────────────────────────────────────────────────────────

    /**
     * List sales orders (paginated).
     *
     * @queryParam status string Filter by status. Example: confirmed
     * @queryParam contact_id integer Filter by contact. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 25
     */
    public function indexOrders(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.read'), 403);

        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->per_page ?? 25), 100);

        $orders = SalesOrder::forTenant($tenantId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->contact_id))
            ->with(['lines', 'createdBy:id,name,email'])
            ->latest()
            ->paginate($perPage);

        // Chantier 19 (Sales re-audit) fix: SalesIndex.vue's KPI cards read
        // data.meta.{month_count,month_revenue,pending_count,delivered_count}
        // from this exact response — empirically confirmed via a real HTTP
        // call that a raw paginator's response()->json() never carried a
        // 'meta' key at all (Laravel's default paginator JSON flattens
        // pagination fields to the top level), so every KPI card on the
        // Sales dashboard has always silently shown '0'/'0 XOF' regardless
        // of real data. Additive — the pre-existing top-level pagination
        // keys (current_page/per_page/total/last_page) two other tests
        // assert on are left untouched.
        $tenantOrders = SalesOrder::forTenant($tenantId);
        $meta = [
            'month_count'     => (clone $tenantOrders)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'month_revenue'   => (float) (clone $tenantOrders)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->where('status', '!=', 'cancelled')
                ->sum('total'),
            'pending_count'   => (clone $tenantOrders)
                ->whereIn('status', ['draft', 'confirmed', 'processing'])
                ->count(),
            'delivered_count' => (clone $tenantOrders)->where('status', 'delivered')->count(),
        ];

        return response()->json(array_merge($orders->toArray(), ['meta' => $meta]));
    }

    /**
     * Create a sales order.
     *
     * @bodyParam contact_id integer ID of the CRM contact. Example: 1
     * @bodyParam account_id integer ID of the CRM account. Example: 2
     * @bodyParam currency string 3-letter currency code. Example: XOF
     * @bodyParam lines array required Array of order lines.
     * @bodyParam lines[].description string required Line description. Example: Widget Pro
     * @bodyParam lines[].quantity number required Quantity. Example: 10
     * @bodyParam lines[].unit_price number required Unit price. Example: 5000
     */
    public function storeOrder(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.create'), 403);

        $validated = $request->validate([
            'contact_id'              => 'nullable|integer',
            'account_id'              => 'nullable|integer',
            'opportunity_id'          => 'nullable|integer',
            'currency'                => 'nullable|string|size:3',
            'notes'                   => 'nullable|string|max:5000',
            'shipping_address'        => 'nullable|array',
            'expected_delivery_date'  => 'nullable|date',
            'lines'                   => 'required|array|min:1',
            'lines.*.product_id'      => 'nullable|integer',
            'lines.*.description'     => 'required|string|max:255',
            'lines.*.quantity'        => 'required|numeric|min:0.001',
            'lines.*.unit_price'      => 'required|numeric|min:0',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_rate'        => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['tenant_id'] = $this->tenantId($request);
        $validated['created_by'] = $request->user()->id;

        $order = $this->service->createOrder($validated);

        return response()->json($order->load('lines'), 201);
    }

    /**
     * Get a single sales order.
     */
    public function showOrder(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.read'), 403);

        // Chantier 22 (volet B): depositInvoice/balanceInvoice eager-loaded
        // so Orders/Show.vue's deposit/balance panel doesn't need a second
        // round trip for each invoice's amount/status.
        $order = SalesOrder::forTenant($this->tenantId($request))
            ->with(['lines', 'createdBy:id,name,email', 'depositInvoice', 'balanceInvoice'])
            ->findOrFail($id);

        return response()->json($order);
    }

    /**
     * Update a sales order (draft only).
     */
    public function updateOrder(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::forTenant($this->tenantId($request))->findOrFail($id);

        if (! $order->isEditable()) {
            return response()->json(['message' => 'Only draft orders can be updated.'], 422);
        }

        $validated = $request->validate([
            'contact_id'             => 'nullable|integer',
            'account_id'             => 'nullable|integer',
            'currency'               => 'nullable|string|size:3',
            'notes'                  => 'nullable|string|max:5000',
            'shipping_address'       => 'nullable|array',
            'expected_delivery_date' => 'nullable|date',
        ]);

        $order->update($validated);

        return response()->json($order->fresh('lines'));
    }

    /**
     * Confirm a sales order.
     *
     * Transitions the order from draft to confirmed.
     */
    public function confirmOrder(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::forTenant($this->tenantId($request))->findOrFail($id);

        try {
            $order = $this->service->confirmOrder($order);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    /**
     * Cancel a sales order.
     *
     * @bodyParam reason string required Cancellation reason. Example: Customer request.
     */
    public function cancelOrder(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::forTenant($this->tenantId($request))->findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $order = $this->service->cancelOrder($order, $validated['reason']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    // ─── Quotations ────────────────────────────────────────────────────────────

    /**
     * List sales quotations (paginated).
     *
     * @queryParam status string Filter by status. Example: sent
     * @queryParam per_page integer Results per page (max 100). Example: 25
     */
    public function indexQuotations(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.read'), 403);

        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->per_page ?? 25), 100);

        $quotations = SalesQuotation::forTenant($tenantId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->with(['createdBy:id,name,email', 'convertedOrder:id,reference,status'])
            ->latest()
            ->paginate($perPage);

        return response()->json($quotations);
    }

    /**
     * Create a quotation.
     *
     * @bodyParam contact_id integer CRM contact ID. Example: 1
     * @bodyParam currency string 3-letter currency code. Example: XOF
     * @bodyParam total number Total amount. Example: 150000
     * @bodyParam valid_until date Expiry date. Example: 2026-06-30
     * @bodyParam notes string Additional notes. Example: Subject to availability.
     */
    public function storeQuotation(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.create'), 403);

        $validated = $request->validate([
            'contact_id'  => 'nullable|integer',
            'currency'    => 'nullable|string|size:3',
            'total'       => 'nullable|numeric|min:0',
            'valid_until' => 'nullable|date|after:today',
            'notes'       => 'nullable|string|max:5000',
        ]);

        $validated['tenant_id'] = $this->tenantId($request);
        $validated['created_by'] = $request->user()->id;

        $quotation = $this->service->createQuotation($validated);

        return response()->json($quotation, 201);
    }

    /**
     * Get a single quotation with its lines.
     *
     * Chantier 10 fix: this endpoint (and every other quotation endpoint below it) had zero
     * permission check and zero tenant scoping at all — SalesQuotation::findOrFail($id) let
     * any authenticated user of any tenant view, edit, send, or convert any other tenant's
     * quotation by guessing its id, unlike the sibling order endpoints above (which already
     * correctly used forTenant()+abort_unless since Chantier 8.5-light). Fixed to match.
     */
    public function showQuotation(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.read'), 403);

        $quotation = SalesQuotation::forTenant($this->tenantId($request))
            ->with(['createdBy:id,name,email', 'convertedOrder:id,reference,status'])
            ->findOrFail($id);

        return response()->json($quotation);
    }

    /**
     * Update a quotation (draft only).
     *
     * @bodyParam contact_id integer CRM contact ID. Example: 1
     * @bodyParam currency string 3-letter currency code. Example: XOF
     * @bodyParam total number Total amount. Example: 200000
     * @bodyParam valid_until date Expiry date. Example: 2026-07-31
     * @bodyParam notes string Additional notes. Example: Updated terms.
     */
    public function updateQuotation(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $quotation = SalesQuotation::forTenant($this->tenantId($request))->findOrFail($id);

        if ($quotation->status !== 'draft') {
            return response()->json(['message' => 'Only draft quotations can be updated.'], 422);
        }

        $validated = $request->validate([
            'contact_id'  => 'nullable|integer',
            'currency'    => 'nullable|string|size:3',
            'total'       => 'nullable|numeric|min:0',
            'valid_until' => 'nullable|date',
            'notes'       => 'nullable|string|max:5000',
        ]);

        $quotation->update($validated);

        return response()->json($quotation->fresh());
    }

    /**
     * Mark a quotation as sent (draft → sent).
     */
    public function sendQuotation(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $quotation = SalesQuotation::forTenant($this->tenantId($request))->findOrFail($id);

        if ($quotation->status !== 'draft') {
            return response()->json(['message' => 'Only draft quotations can be marked as sent.'], 422);
        }

        $quotation->update(['status' => 'sent']);

        return response()->json($quotation->fresh());
    }

    /**
     * Convert a quotation into a sales order.
     */
    public function convertQuotation(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $quotation = SalesQuotation::forTenant($this->tenantId($request))->findOrFail($id);

        try {
            $order = $this->service->convertQuotationToOrder($quotation);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Quotation converted to order successfully.',
            'order'   => $order->load('lines'),
        ]);
    }

    /**
     * Update order status (confirm/cancel/ship/deliver).
     *
     * @urlParam id integer required The order ID. Example: 1
     * @bodyParam status string required New status. One of: confirmed, cancelled, processing, shipped, delivered. Example: confirmed
     * @bodyParam reason string Cancellation reason (required when status=cancelled). Example: Customer request.
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::forTenant($this->tenantId($request))->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:confirmed,cancelled,processing,shipped,delivered',
            'reason' => 'nullable|string|max:1000',
        ]);

        $newStatus = $validated['status'];

        // Define allowed transitions
        $allowedTransitions = [
            'draft'      => ['confirmed', 'cancelled'],
            'confirmed'  => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped'    => ['delivered'],
            'delivered'  => [],
            'cancelled'  => [],
            'returned'   => [],
        ];

        $currentStatus = $order->status;

        if (! in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            return response()->json([
                'message' => "Cannot transition order from '{$currentStatus}' to '{$newStatus}'.",
            ], 422);
        }

        try {
            if ($newStatus === 'confirmed') {
                $order = $this->service->confirmOrder($order);
            } elseif ($newStatus === 'cancelled') {
                $reason = $validated['reason'] ?? 'No reason provided.';
                $order  = $this->service->cancelOrder($order, $reason);
            } else {
                $updates = ['status' => $newStatus];
                $order->update($updates);
                $order = $order->fresh();
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    // ─── Chantier 22 (volet B) — cycle acompte/solde ─────────────────────────────

    /**
     * Demander un acompte (crée et lie une facture d'acompte réelle).
     *
     * @bodyParam percent number required Pourcentage d'acompte (0-100). Example: 30
     */
    public function requestDeposit(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::forTenant($this->tenantId($request))->findOrFail($id);
        $validated = $request->validate(['percent' => 'required|numeric|min:0.01|max:100']);

        try {
            $order = $this->depositService->requestDeposit($order, (float) $validated['percent'], $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    /**
     * Demander le solde restant (crée et lie une facture de solde réelle).
     */
    public function requestBalance(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::forTenant($this->tenantId($request))->findOrFail($id);

        try {
            $order = $this->depositService->requestBalance($order, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    /**
     * Enregistrer le paiement de l'acompte (facture + écriture comptable réelles).
     *
     * @bodyParam amount number required Montant encaissé. Example: 300000
     * @bodyParam method string Moyen de paiement (mvola, virement, espèces, ...). Example: mvola
     * @bodyParam reference string Référence du paiement. Example: MVOLA-12345
     */
    public function payDeposit(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::forTenant($this->tenantId($request))->findOrFail($id);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
        ]);

        try {
            $order = $this->depositService->recordDepositPayment(
                $order,
                (float) $validated['amount'],
                $validated['method'] ?? null,
                $validated['reference'] ?? null,
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    /**
     * Enregistrer le paiement du solde (facture + écriture comptable réelles).
     */
    public function payBalance(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::forTenant($this->tenantId($request))->findOrFail($id);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
        ]);

        try {
            $order = $this->depositService->recordBalancePayment(
                $order,
                (float) $validated['amount'],
                $validated['method'] ?? null,
                $validated['reference'] ?? null,
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }
}
