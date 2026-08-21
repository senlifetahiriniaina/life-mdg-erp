<?php

namespace Modules\Validation\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Services\ApprovalRequestService;

/**
 * @group Controllers - Approval Request
 *
 * Manage Approval Request resources.
 */
class ApprovalRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * Chantier 32.7: the module's OWN allowlist of `approvable_type` aliases
     * — deliberately NOT `array_keys(Relation::morphMap())`, which was the
     * real (pre-fix) validation rule below. `Relation::morphMap()` is a
     * single global static array shared by the whole framework: once
     * `Modules\Helpdesk\Providers\HelpdeskServiceProvider` also boots and
     * registers its own aliases ('contact', 'product', 'sales_order',
     * 'project', 'shipment', 'employee', plus its own 'invoice'/
     * 'purchase_order'), `array_keys(Relation::morphMap())` resolves to ALL
     * 8 of them merged together — confirmed empirically via
     * `Relation::morphMap()` at runtime — not just the 2 types this module's
     * own `ValidationServiceProvider::registerApprovableMorphMap()`
     * registers and that this controller's own code comment claims are the
     * allowlist. Any authenticated user with the `create` ability could
     * therefore wire an ApprovalRequest to a Contact/Product/SalesOrder/
     * Project/Shipment/Employee record — types this module has no rule/
     * hierarchy/routing logic for at all.
     */
    private const APPROVABLE_ALIASES = ['invoice', 'purchase_order'];

    public function __construct(protected ApprovalRequestService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        $query = ApprovalRequest::with(['workflow', 'requester', 'approver', 'approvable']);

        // Chantier 31: confirmed empirically via a real cross-company HTTP
        // request that this listing had zero tenant scoping of any kind —
        // any authenticated admin/manager/approver of ANY company could
        // list every OTHER company's pending approval requests (invoices,
        // purchase orders, ...) via this single endpoint. super-admin
        // (Gate::before bypass) is the one deliberate exception, matching
        // its platform-operator role everywhere else in this app.
        if (! $request->user()->hasRole('super-admin')) {
            // COALESCE(...,0), not a bare where('company_id', ...) — matches
            // ApprovalRequestPolicy::sameCompany()'s NULL-normalized-to-0
            // sentinel exactly, rather than relying on Eloquent's implicit
            // where(col, null) -> whereNull() conversion for the null case.
            $query->whereRaw('COALESCE(company_id, 0) = ?', [$request->user()->company_id ?? 0]);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('module')) {
            $query->whereHas('workflow', fn ($q) => $q->where('module_name', $request->module));
        }

        if ($request->has('for_user')) {
            $userId = $request->for_user === 'me' ? auth()->id() : $request->for_user;
            $query->where('requested_by', $userId);
        }

        $requests = $query->paginate(15)->withQueryString();

        $user = $request->user();
        $requests->getCollection()->transform(function (ApprovalRequest $r) use ($user) {
            $r->awaiting_my_action = $r->status === 'pending'
                && ($user->id === $r->approver_id || $user->hasAnyRole(['admin', 'manager']));

            return $r;
        });

        return $requests;
    }

    public function store(Request $request)
    {
        $this->authorize('create', ApprovalRequest::class);

        $data = $request->validate([
            // Chantier 32.7: restricted to this module's OWN allowlist (see
            // the class-level APPROVABLE_ALIASES docblock) — never the raw,
            // app-wide Relation::morphMap(), which also carries aliases this
            // module has no business processing at all.
            'approvable_type' => ['required', 'string', Rule::in(self::APPROVABLE_ALIASES)],
            'approvable_id' => 'required|integer',
            'workflow_id' => 'required|integer|exists:validation_approval_workflows,id',
        ]);

        $approvableClass = Relation::getMorphedModel($data['approvable_type']);
        $approvable = $approvableClass::findOrFail($data['approvable_id']);
        $workflow = ApprovalWorkflow::findOrFail($data['workflow_id']);

        // Chantier 32.7: cross-tenant IDOR — an authenticated admin/manager of
        // ANY company could otherwise wire an ApprovalRequest to another
        // company's PurchaseOrder purely by guessing/enumerating its id (no
        // ownership check existed here at all). Duck-typed rather than
        // hardcoded per consumer module (this controller must not assume
        // which fields Achats/Accounting/HR models carry) — a `company_id`
        // column is checked when the approvable model actually has one
        // (true for PurchaseOrder); Invoice has no company_id column at all
        // on this schema (a separate, already-documented Accounting gap),
        // so this check is a no-op for invoices, not a false negative.
        $requester = $request->user();

        if (
            !$requester->hasRole('super-admin')
            && \Illuminate\Support\Facades\Schema::hasColumn($approvable->getTable(), 'company_id')
            && (int) ($approvable->company_id ?? 0) !== (int) ($requester->company_id ?? 0)
        ) {
            abort(404);
        }

        $approvalRequest = $this->service->createApprovalRequest($approvable, $workflow, $requester);

        return response()->json($approvalRequest, 201);
    }

    public function show(ApprovalRequest $approval_request)
    {
        $this->authorize('view', $approval_request);

        return $approval_request->load(['workflow', 'requester', 'actions.approver', 'history']);
    }

    public function approve(Request $request, ApprovalRequest $approval_request)
    {
        $this->authorize('approve', $approval_request);

        $validated = $request->validate([
            'comment' => 'nullable|string',
        ]);

        $this->service->approveRequest($approval_request, auth()->user(), $validated['comment'] ?? null);

        return $approval_request->refresh();
    }

    public function reject(Request $request, ApprovalRequest $approval_request)
    {
        $this->authorize('reject', $approval_request);

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $this->service->rejectRequest($approval_request, auth()->user(), $validated['reason']);

        return $approval_request->refresh();
    }

    public function delegate(Request $request, ApprovalRequest $approval_request)
    {
        $this->authorize('delegate', $approval_request);

        $validated = $request->validate([
            'to_user_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string',
        ]);

        $to = User::findOrFail($validated['to_user_id']);

        // Chantier 32.7: delegateApproval() reassigns approver_id to
        // WHATEVER user_id the request body names, with no check that the
        // delegate belongs to the request's own hierarchy/company at all —
        // confirmed empirically that the assigned approver (or a
        // same-company admin/manager) could delegate a purchase-order/
        // invoice approval to an arbitrary user in the users table,
        // completely bypassing the amount-tiered role-based routing the
        // request was created against. A super-admin (platform operator)
        // is exempt, matching the Gate::before bypass used everywhere else
        // in this app.
        if (
            !$request->user()->hasRole('super-admin')
            && (int) ($to->company_id ?? 0) !== (int) ($approval_request->company_id ?? 0)
        ) {
            return response()->json([
                'message' => 'Cannot delegate to a user outside the request\'s own company.',
            ], 422);
        }

        $this->service->delegateApproval(
            $approval_request,
            auth()->user(),
            $to,
            $validated['reason'] ?? null
        );

        return $approval_request->refresh();
    }

    /**
     * Chantier 19 Lot 3: index()/show() were already gated by
     * ApprovalRequestPolicy (Chantier 8.5sv), but history() — a sibling
     * endpoint exposing the exact same requester/approver/reason detail —
     * had zero authorize() call at all. Any authenticated user could read
     * any approval request's full decision history by id, bypassing the
     * view() ability entirely. Fixed to match show()'s existing gate.
     */
    public function history(ApprovalRequest $approval_request)
    {
        $this->authorize('view', $approval_request);

        return $approval_request->history()->get();
    }
}
