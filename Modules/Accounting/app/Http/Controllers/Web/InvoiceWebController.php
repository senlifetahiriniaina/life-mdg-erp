<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\InvoiceApprovalService;

class InvoiceWebController extends Controller
{
    public function __construct(private readonly InvoiceApprovalService $approvalService) {}

    public function index(Request $request): Response
    {
        $invoices = Invoice::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('invoice_date')
            ->paginate(25)->withQueryString();

        return Inertia::render('Accounting/Invoices/Index', ['invoices' => $invoices]);
    }

    /**
     * Had no route at all until now — InvoiceApproval/Show.vue existed with
     * a full UI but nothing in routes/web.php ever pointed to it.
     */
    public function showApproval(Invoice $invoice): Response
    {
        $level = $this->approvalService->getApprovalLevel((float) $invoice->total);
        $user = request()->user();

        return Inertia::render('Accounting/InvoiceApproval/Show', [
            'invoice' => $invoice->only(['id', 'number', 'partner_name', 'customer_name', 'total', 'currency', 'invoice_date', 'due_date', 'approval_status']),
            'level' => $level,
            'label' => $this->approvalService->getLevelLabel($level),
            'levels' => collect([1, 2, 3])->map(fn ($l) => ['level' => $l, 'label' => $this->approvalService->getLevelLabel($l)]),
            'chain' => $this->approvalService->getApprovalChain($invoice),
            'can_approve' => $invoice->approval_status === 'pending'
                && $user?->hasAnyRole(['accountant', 'finance-manager', 'manager', 'admin']),
        ]);
    }
}
