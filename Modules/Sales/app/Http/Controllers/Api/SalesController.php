<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesQuotation;
use Modules\Sales\Services\SalesService;

/**
 * @group Sales
 *
 * Manage sales orders and quotations.
 */
class SalesController extends Controller
{
    public function __construct(private readonly SalesService $service) {}

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

        $tenantId = $request->user()->tenant_id ?? 1;
        $perPage  = min((int) ($request->per_page ?? 25), 100);

        $orders = SalesOrder::forTenant($tenantId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->contact_id))
            ->with(['lines', 'createdBy:id,name,email'])
            ->latest()
            ->paginate($perPage);

        return response()->json($orders);
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

        $validated['tenant_id'] = $request->user()->tenant_id ?? 1;
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

        $order = SalesOrder::with(['lines', 'createdBy:id,name,email'])->findOrFail($id);

        return response()->json($order);
    }

    /**
     * Update a sales order (draft only).
     */
    public function updateOrder(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $order = SalesOrder::findOrFail($id);

        if (! $order->isEditable()) {
            return response()->json(['message' => 'Only draft or confirmed orders can be updated.'], 422);
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
    public function confirmOrder(int $id): JsonResponse
    {
        $order = SalesOrder::findOrFail($id);

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
        $order = SalesOrder::findOrFail($id);

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

        $tenantId = $request->user()->tenant_id ?? 1;
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
        $validated = $request->validate([
            'contact_id'  => 'nullable|integer',
            'currency'    => 'nullable|string|size:3',
            'total'       => 'nullable|numeric|min:0',
            'valid_until' => 'nullable|date|after:today',
            'notes'       => 'nullable|string|max:5000',
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id ?? 1;
        $validated['created_by'] = $request->user()->id;

        $quotation = $this->service->createQuotation($validated);

        return response()->json($quotation, 201);
    }

    /**
     * Get a single quotation with its lines.
     */
    public function showQuotation(int $id): JsonResponse
    {
        $quotation = SalesQuotation::with(['createdBy:id,name,email', 'convertedOrder:id,reference,status'])
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
        $quotation = SalesQuotation::findOrFail($id);

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
    public function sendQuotation(int $id): JsonResponse
    {
        $quotation = SalesQuotation::findOrFail($id);

        if ($quotation->status !== 'draft') {
            return response()->json(['message' => 'Only draft quotations can be marked as sent.'], 422);
        }

        $quotation->update(['status' => 'sent']);

        return response()->json($quotation->fresh());
    }

    /**
     * Convert a quotation into a sales order.
     */
    public function convertQuotation(int $id): JsonResponse
    {
        $quotation = SalesQuotation::findOrFail($id);

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
        $order = SalesOrder::findOrFail($id);

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
}
