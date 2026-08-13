<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\FreightInvoice;
use Modules\Logistics\Services\FreightBillingService;

/**
 * @group Logistics - Freight Invoices
 */
class FreightInvoiceController extends Controller
{
    public function __construct(private readonly FreightBillingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = FreightInvoice::with('carrier:id,name', 'shipment:id,reference', 'creator:id,name')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('carrier_id'), fn ($q, $v) => $q->where('carrier_id', $v))
            ->when($request->input('shipment_id'), fn ($q, $v) => $q->where('shipment_id', $v))
            ->when($request->input('date_from'), fn ($q, $v) => $q->where('invoice_date', '>=', $v))
            ->when($request->input('date_to'), fn ($q, $v) => $q->where('invoice_date', '<=', $v))
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($sq) => $sq->where('invoice_number', 'like', "%{$v}%")
                ->orWhere('carrier_invoice_ref', 'like', "%{$v}%")))
            ->latest('invoice_date')
            ->paginate(20);

        return response()->json($q);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => 'nullable|integer|exists:logistics_shipments,id',
            'carrier_id' => 'required|integer|exists:logistics_carriers,id',
            'type' => 'required|in:payable,receivable',
            'invoice_number' => 'nullable|string|max:100',
            'carrier_invoice_ref' => 'nullable|string|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'currency' => 'required|string|size:3',
            'quoted_amount' => 'nullable|numeric|min:0',
            'invoiced_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';
        $data['variance_amount'] = isset($data['quoted_amount'])
            ? round((float) $data['invoiced_amount'] - (float) $data['quoted_amount'], 2)
            : null;

        if (empty($data['invoice_number'])) {
            $data['invoice_number'] = 'FRT-'.now()->format('Ymd').'-'.str_pad(
                (string) (FreightInvoice::whereDate('created_at', today())->count() + 1),
                4,
                '0',
                STR_PAD_LEFT
            );
        }

        return response()->json(FreightInvoice::create($data), 201);
    }

    public function show(FreightInvoice $freightInvoice): JsonResponse
    {
        return response()->json(
            $freightInvoice->load('carrier', 'shipment:id,reference,transport_mode', 'creator:id,name', 'approver:id,name')
        );
    }

    public function update(Request $request, FreightInvoice $freightInvoice): JsonResponse
    {
        abort_if(
            in_array($freightInvoice->status, ['approved', 'paid'], true),
            422,
            'Cannot edit an approved or paid invoice.'
        );

        $data = $request->validate([
            'invoice_number' => 'nullable|string|max:100',
            'carrier_invoice_ref' => 'nullable|string|max:100',
            'invoice_date' => 'sometimes|date',
            'due_date' => 'nullable|date',
            'invoiced_amount' => 'sometimes|numeric|min:0',
            'quoted_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (isset($data['invoiced_amount']) || isset($data['quoted_amount'])) {
            $invoiced = (float) ($data['invoiced_amount'] ?? $freightInvoice->invoiced_amount);
            $quoted = (float) ($data['quoted_amount'] ?? $freightInvoice->quoted_amount);
            if ($quoted > 0) {
                $data['variance_amount'] = round($invoiced - $quoted, 2);
            }
        }

        $freightInvoice->update($data);

        return response()->json($freightInvoice->fresh());
    }

    public function destroy(FreightInvoice $freightInvoice): JsonResponse
    {
        abort_if(
            in_array($freightInvoice->status, ['approved', 'paid'], true),
            422,
            'Cannot delete an approved or paid invoice.'
        );

        $freightInvoice->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, FreightInvoice $freightInvoice): JsonResponse
    {
        abort_if(
            in_array($freightInvoice->status, ['approved', 'paid', 'disputed'], true),
            422,
            'Invoice cannot be approved in its current state.'
        );

        $this->service->approve($freightInvoice, $request->user()->id);

        return response()->json($freightInvoice->fresh());
    }

    public function dispute(Request $request, FreightInvoice $freightInvoice): JsonResponse
    {
        abort_if(
            in_array($freightInvoice->status, ['approved', 'paid', 'disputed'], true),
            422,
            'Invoice cannot be disputed in its current state.'
        );

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->service->dispute($freightInvoice, $data['reason']);

        return response()->json($freightInvoice->fresh());
    }
}
