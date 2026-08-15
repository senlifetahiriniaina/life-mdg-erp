<?php

namespace Modules\Accounting\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreInvoiceRequest;
use Modules\Accounting\Http\Resources\InvoiceResource;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\AccountingService;

/**
 * @group Accounting
 *
 * Manage Invoice resources in Accounting module.
 */
class InvoiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected AccountingService $service) {}

    public function index(Request $request)
    {
        $query = Invoice::with('account', 'lineItems', 'payments');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->customer_id) {
            $query->where(function ($q) use ($request) {
                $q->where('partner_id', $request->customer_id)
                  ->orWhere('customer_id', $request->customer_id);
            });
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where('number', 'like', "%{$search}%");
        }

        if ($request->invoice_date_from) {
            $query->where('invoice_date', '>=', $request->invoice_date_from);
        }

        if ($request->invoice_date_to || $request->date_to) {
            $query->where('invoice_date', '<=', $request->invoice_date_to ?? $request->date_to);
        }

        $sortParam = $request->sort ?? '-invoice_date';
        $sortDir = str_starts_with($sortParam, '-') ? 'desc' : 'asc';
        $sortCol = ltrim($sortParam, '-');
        $allowed = ['number', 'invoice_date', 'total', 'status', 'type'];
        if (in_array($sortCol, $allowed)) {
            $query->orderBy($sortCol, $sortDir);
        }

        $perPage = (int) ($request->per_page ?? 15);
        return response()->json($query->paginate($perPage));
    }

    public function store(StoreInvoiceRequest $request)
    {
        $data = $request->validated();

        // Auto-generate invoice number if not provided
        if (empty($data['number'])) {
            $data['number'] = 'INV-' . now()->format('Y') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);
        }

        // Default type
        if (empty($data['type'])) {
            $data['type'] = 'invoice';
        }

        // Map customer_id to partner_id (keep both)
        if (!empty($data['customer_id'])) {
            $data['partner_id'] = $data['customer_id'];
        }

        // Default status
        $data['status'] = $data['status'] ?? 'draft';
        $data['created_by'] = auth()->id();

        // Calculate totals from lines if provided
        $lines = $data['lines'] ?? $data['line_items'] ?? [];
        if (!empty($lines)) {
            $subtotal = 0;
            $taxAmount = 0;
            foreach ($lines as $line) {
                $qty = (float) ($line['quantity'] ?? $line['qty'] ?? 1);
                $price = (float) ($line['unit_price'] ?? 0);
                $taxRate = (float) ($line['tax_rate'] ?? 0);
                $lineSubtotal = $qty * $price;
                $subtotal += $lineSubtotal;
                $taxAmount += $lineSubtotal * ($taxRate / 100);
            }
            $data['subtotal'] = $subtotal;
            $data['tax_amount'] = $taxAmount;
            if (empty($data['total'])) {
                $data['total'] = $subtotal + $taxAmount;
            }
        }

        $invoice = Invoice::create($data);

        return response()->json(new InvoiceResource($invoice), 201);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('account', 'lineItems', 'payments');
        return new InvoiceResource($invoice);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return response()->json(['message' => 'Only draft invoices can be updated.'], 403);
        }

        $data = $request->validate([
            'total' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'due_date' => 'nullable|date',
            'invoice_date' => 'nullable|date',
            'status' => 'nullable|string|in:draft,sent,paid,overdue,cancelled',
        ]);

        $invoice->update(array_filter($data, fn ($v) => $v !== null));

        return new InvoiceResource($invoice->fresh());
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'status' => 'required|string|in:draft,sent,paid,overdue,cancelled',
        ]);

        $invoice->update(['status' => $data['status']]);

        return new InvoiceResource($invoice->fresh());
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            // A 'sent' invoice has been legally issued to the customer -> forbidden to delete.
            // Other non-draft states (paid/overdue/cancelled) are an unprocessable business state.
            $code = $invoice->status === 'sent' ? 403 : 422;

            return response()->json(['message' => 'Only draft invoices can be deleted.'], $code);
        }

        $invoice->delete();

        return response()->noContent();
    }

    public function outstanding()
    {
        $invoices = Invoice::where('status', 'sent')
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->get();

        return InvoiceResource::collection($invoices);
    }

    public function overdue()
    {
        $invoices = Invoice::where('status', 'sent')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->get();

        return InvoiceResource::collection($invoices);
    }

    public function summary()
    {
        $byStatus = Invoice::selectRaw('status, COUNT(*) as count, SUM(total) as total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn ($row) => ['count' => $row->count, 'total_amount' => (float) $row->total_amount]);

        return response()->json([
            'by_status' => $byStatus,
            'total_outstanding' => (float) Invoice::where('status', 'sent')->sum('total'),
            'total_overdue' => (float) Invoice::where('status', 'sent')->where('due_date', '<', now())->sum('total'),
        ]);
    }

    public function agedReceivables()
    {
        $now = now();

        $invoices = Invoice::where('status', 'sent')
            ->whereColumn('total', '>', 'amount_paid')
            ->get();

        $buckets = [
            'current' => 0.0,
            'over_30_days' => 0.0,
            'over_60_days' => 0.0,
            'over_90_days' => 0.0,
        ];

        foreach ($invoices as $inv) {
            $overdueDays = Carbon::parse($inv->due_date)->diffInDays($now, false);
            $outstanding = (float) $inv->total - (float) ($inv->amount_paid ?? 0);

            if ($overdueDays <= 0) {
                $buckets['current'] += $outstanding;
            } elseif ($overdueDays <= 30) {
                $buckets['over_30_days'] += $outstanding;
            } elseif ($overdueDays <= 60) {
                $buckets['over_60_days'] += $outstanding;
            } else {
                $buckets['over_90_days'] += $outstanding;
            }
        }

        return response()->json($buckets);
    }

    public function markPaid(Invoice $invoice)
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        return new InvoiceResource($invoice->fresh());
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'method' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        $currentPaid = (float) ($invoice->amount_paid ?? 0);
        $newPaid = $currentPaid + $validated['amount'];

        if ($newPaid > (float) $invoice->total) {
            return response()->json(['message' => 'Payment amount exceeds invoice total.', 'errors' => ['amount' => ['Payment exceeds total']]], 422);
        }

        $invoice->update([
            'amount_paid' => $newPaid,
            'paid_amount' => $newPaid,
            'status' => $newPaid >= (float) $invoice->total ? 'paid' : $invoice->status,
            'paid_at' => $newPaid >= (float) $invoice->total ? now() : null,
        ]);

        return response()->json(new InvoiceResource($invoice->fresh()), 201);
    }
}
