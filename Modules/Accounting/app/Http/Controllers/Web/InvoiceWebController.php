<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
     * Had no route at all until now — InvoiceApproval/Index.vue existed with
     * a full UI but fetched a nonexistent /api/v1/accounting/approval-queue
     * endpoint and used <router-link> (this app is Inertia-only, no
     * vue-router). Mirrors showApproval()'s per-invoice shape below.
     */
    public function approvalQueue(): Response
    {
        $invoices = Invoice::where('approval_status', 'pending')
            ->latest('created_at')
            ->get()
            ->map(function (Invoice $invoice) {
                $level = $this->approvalService->getApprovalLevel((float) $invoice->total);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'supplier_name' => $invoice->partner_name ?: $invoice->customer_name,
                    'total_amount' => (float) $invoice->total,
                    'currency' => $invoice->currency,
                    'approval_status' => $invoice->approval_status,
                    'required_approval_level' => $level,
                    'required_approval_label' => $this->approvalService->getLevelLabel($level),
                    'days_pending' => (int) $invoice->created_at->diffInDays(now()),
                ];
            });

        $analytics = $this->approvalService->getAnalytics(now()->subDays(30), now());

        return Inertia::render('Accounting/InvoiceApproval/Index', [
            'invoices' => $invoices->values(),
            'levels' => collect([1, 2, 3])->map(fn ($l) => ['level' => $l, 'label' => $this->approvalService->getLevelLabel($l)]),
            'stats' => [
                'pending_count' => $invoices->count(),
                'urgent_count' => $invoices->where('days_pending', '>', 5)->count(),
                'approval_rate_percent' => round($analytics['approved_pct'] ?? 0, 1),
            ],
        ]);
    }

    /**
     * Had no route at all until now — Invoices/Show.vue existed with a full
     * UI but nothing in routes/web.php ever pointed to it. The model has no
     * `customer` relation (only flat customer_id/customer_name columns), so
     * synthesize the shape the page expects from those columns.
     */
    public function show(Invoice $invoice): Response
    {
        $invoice->load(['lineItems', 'payments']);

        return Inertia::render('Accounting/Invoices/Show', [
            'invoice' => array_merge($invoice->toArray(), [
                'customer' => [
                    'id' => $invoice->customer_id,
                    'name' => $invoice->customer_name ?: $invoice->partner_name,
                ],
            ]),
        ]);
    }

    /**
     * Had no route at all until now — Invoices/Form.vue existed with a full
     * UI (create + edit) but nothing in routes/web.php ever pointed to it.
     */
    public function create(): Response
    {
        return Inertia::render('Accounting/Invoices/Form');
    }

    public function edit(Invoice $invoice): Response
    {
        return Inertia::render('Accounting/Invoices/Form', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Had no route at all until now — InvoiceApproval/Show.vue existed with
     * a full UI but nothing in routes/web.php ever pointed to it.
     */
    public function showApproval(Invoice $invoice): Response
    {
        $level = $this->approvalService->getApprovalLevel((float) $invoice->total);
        $user = request()->user();

        // Chantier 31: this web page had zero role/policy gate of any
        // kind — `routes/web.php`'s whole group is only `auth`, matching
        // this module's established convention of leaning on the outer
        // route-level `role:` gate the sibling API routes carry, which
        // this web route never had either. Confirmed empirically: any
        // authenticated user, including one holding no accounting-related
        // role at all, could open this page and read the full approval
        // chain (amounts, approver names, comments) for any invoice.
        // Fixed by authorizing against the real ApprovalRequest via the
        // same ApprovalRequestPolicy::view() used by the JSON API's
        // index() (requester, assigned approver, or admin/manager) —
        // matching the case where no request exists yet (nothing sensitive
        // to gate) left open, same as the API.
        $existingRequest = $this->approvalService->findLatestRequest($invoice);
        if ($existingRequest) {
            abort_unless($user && Gate::forUser($user)->allows('view', $existingRequest), 403);
        }

        // Chantier 31: `can_approve` used to only check the broad outer
        // route-gate role list (accountant/finance-manager/manager/admin),
        // the same over-permissive check the real approve()/reject() API
        // endpoints have now been fixed to reject — showing the button to
        // every accounting-role user regardless of whether they're the
        // actually-assigned approver, then letting a real 403 surface as a
        // confusing generic error on click. Now mirrors the real backend
        // check (ApprovalRequestPolicy::approve(), via the real
        // ApprovalRequest when one exists) so the button only appears when
        // the click would actually succeed.
        $canApprove = $existingRequest
            ? ($user && Gate::forUser($user)->allows('approve', $existingRequest))
            : false;

        return Inertia::render('Accounting/InvoiceApproval/Show', [
            'invoice' => $invoice->only(['id', 'number', 'partner_name', 'customer_name', 'total', 'currency', 'invoice_date', 'due_date', 'approval_status']),
            'level' => $level,
            'label' => $this->approvalService->getLevelLabel($level),
            'levels' => collect([1, 2, 3])->map(fn ($l) => ['level' => $l, 'label' => $this->approvalService->getLevelLabel($l)]),
            'chain' => $this->approvalService->getApprovalChain($invoice),
            'can_approve' => $canApprove,
        ]);
    }
}
