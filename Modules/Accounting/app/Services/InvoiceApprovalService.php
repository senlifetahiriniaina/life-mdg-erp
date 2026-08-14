<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use App\Models\User;
use Carbon\Carbon;
use Modules\Accounting\Models\ApprovalStep;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceApproval;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Models\HierarchyLevel;
use Modules\Validation\Models\LevelApprover;
use Modules\Validation\Services\ApprovalRequestService;
use Modules\Validation\Services\ApprovalRoutingResolver;

/**
 * OHADA-compliant 3-level invoice approval, routed through the shared
 * Modules\Validation engine (ApprovalWorkflow -> ApprovalRule ->
 * ApprovalRequest) instead of its own bespoke, disconnected state — the
 * previous version created an InvoiceApproval row with columns
 * (required_level, approved_by_level_N, tenant_id) that don't exist on
 * that model/table, and updated acc_invoices.approval_status /
 * Invoice::amount, neither of which exist on the real Invoice schema
 * (real amount column is `total`). It had never run successfully.
 *
 * Validation is the write authority; accounting_invoice_approvals /
 * accounting_approval_steps are kept as a read-projection so the existing
 * InvoiceApproval Vue UI doesn't need to be rewritten.
 */
class InvoiceApprovalService
{
    /**
     * Bootstrap defaults ONLY — used by getOrCreateWorkflow()'s
     * firstOrCreate() the first time this workflow is seeded. Once seeded,
     * these are no longer authoritative: getApprovalLevel()/getThreshold()
     * read the live validation_approval_rules rows instead, so editing a
     * rule's condition_value through the (now admin-gated) Validation API
     * actually changes routing, exactly like Achats\ApprovalRoutingService
     * already does. Before this fix, editing those rows had zero effect on
     * submitForApproval()'s actual routing level.
     */
    private const THRESHOLDS = [
        1 => 100_000,    // <= 100K XOF - manager
        2 => 500_000,    // <= 500K XOF - finance-manager
        3 => 10_000_000, // <= 10M XOF - admin (above that, still level 3)
    ];

    /** Bootstrap defaults only — see THRESHOLDS docblock. */
    private const LEVEL_ROLES = [
        1 => 'manager',
        2 => 'finance-manager',
        3 => 'admin',
    ];

    public function __construct(
        private readonly ApprovalRequestService $approvalService,
        private readonly ApprovalRoutingResolver $resolver,
    ) {}

    /**
     * Live-reads the seeded workflow's rules (descending rule_order —
     * same "most specific tier wins" convention ApprovalRoutingResolver
     * already uses) instead of the bootstrap THRESHOLDS constant.
     */
    public function getApprovalLevel(float $amount): int
    {
        $rule = $this->matchingRule($amount);

        return $rule ? (int) $rule->rule_order : 3;
    }

    protected function matchingRule(float $amount): ?ApprovalRule
    {
        $workflow = $this->getOrCreateWorkflow();

        return $workflow->rules()
            ->orderByDesc('rule_order')
            ->get()
            ->first(fn (ApprovalRule $rule) => $rule->evaluateCondition((object) ['total' => $amount]));
    }

    public function getLevelLabel(int $level): string
    {
        return match ($level) {
            1 => 'Manager Approval (≤ 100K XOF)',
            2 => 'Finance Manager Approval (≤ 500K XOF)',
            3 => 'Admin Approval (> 500K XOF)',
            default => 'Unknown',
        };
    }

    /**
     * Live threshold for a level, read from its rule's condition_value —
     * reflects real-time edits made through the Validation API instead of
     * the bootstrap constant.
     */
    public function getThreshold(int $level): float
    {
        $rule = $this->getOrCreateWorkflow()->rules()->where('rule_order', $level)->first();

        return $rule ? (float) $rule->condition_value : (self::THRESHOLDS[$level] ?? 0);
    }

    /**
     * Idempotently seeds the single Accounting invoice-approval workflow
     * (3 amount-tiered rules, each with a matching 1-level role hierarchy)
     * — mirrors the pattern used by Achats\ApprovalRoutingService for
     * purchase orders.
     */
    public function getOrCreateWorkflow(): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::firstOrCreate(
            ['name' => 'Invoice Approval (OHADA thresholds)'],
            [
                'description' => 'Amount-tiered invoice approval routing',
                'module_name' => 'Accounting',
                'is_active' => true,
                'created_by' => User::first()->id ?? 1,
            ]
        );

        foreach (self::THRESHOLDS as $level => $threshold) {
            $hierarchy = ApprovalHierarchy::firstOrCreate(
                ['name' => "Invoice Approval - Level {$level}"],
                [
                    'module_name' => 'Accounting',
                    'is_active' => true,
                    'escalation_role' => 'admin',
                ]
            );

            $hierarchyLevel = HierarchyLevel::firstOrCreate(
                ['hierarchy_id' => $hierarchy->id, 'level_order' => 1],
                ['title' => $this->getLevelLabel($level), 'approver_count' => 1, 'delegation_allowed' => true]
            );

            LevelApprover::firstOrCreate(
                ['hierarchy_level_id' => $hierarchyLevel->id, 'role' => self::LEVEL_ROLES[$level]],
                ['is_active' => true]
            );

            // Level 1 is bounded above by its own threshold; levels 2+ are
            // bounded below by the *previous* level's threshold. Combined
            // with ApprovalRoutingResolver evaluating rules in descending
            // rule_order (highest/most-specific tier first), this makes the
            // three rules mutually exclusive despite each only carrying a
            // single one-sided comparison — matches the convention already
            // established by Achats\ApprovalRoutingService::createDefaultWorkflows().
            $operator = $level === 1 ? '<=' : '>';
            $value = $level === 1 ? $threshold : self::THRESHOLDS[$level - 1];

            ApprovalRule::firstOrCreate(
                ['workflow_id' => $workflow->id, 'rule_order' => $level],
                [
                    'condition_type' => 'amount',
                    'condition_operator' => $operator,
                    'condition_value' => (string) $value,
                    'required_approvers_count' => 1,
                    'approval_mode' => 'sequential',
                    'hierarchy_id' => $hierarchy->id,
                ]
            );
        }

        return $workflow;
    }

    public function submitForApproval(Invoice $invoice, User $submittedBy): ApprovalRequest
    {
        $workflow = $this->getOrCreateWorkflow();
        $level = $this->getApprovalLevel((float) $invoice->total);

        $request = $this->approvalService->createApprovalRequest($invoice, $workflow, $submittedBy);
        $this->approvalService->submitApprovalRequest($request);

        $approvers = $this->resolver->resolveApprovers($request);
        if ($approvers->isNotEmpty()) {
            $request->update(['approver_id' => $approvers->first()->id]);
        }

        $invoice->update(['approval_status' => 'pending']);

        $projection = InvoiceApproval::updateOrCreate(
            ['invoice_id' => $invoice->id, 'status' => 'pending'],
            [
                'approval_request_id' => $request->id,
                'invoice_type' => $invoice->type,
                'invoice_number' => $invoice->number,
                'amount' => $invoice->total,
                'currency' => $invoice->currency,
                'submitted_by' => $submittedBy->id,
                'submitted_at' => now(),
                'current_level' => 1,
            ]
        );

        ApprovalStep::updateOrCreate(
            ['approval_id' => $projection->id, 'level' => $level],
            [
                'required_role' => self::LEVEL_ROLES[$level],
                'threshold_amount' => $this->getThreshold($level),
                'action' => 'pending',
            ]
        );

        $this->notifyApprovers($invoice, $level);

        return $request;
    }

    public function approve(Invoice $invoice, User $approver, ?string $notes = null): ApprovalRequest
    {
        $request = $this->latestPendingRequest($invoice);

        $this->approvalService->approveRequest($request, $approver, $notes);

        $invoice->update(['approval_status' => 'approved']);

        InvoiceApproval::where('invoice_id', $invoice->id)
            ->where('approval_request_id', $request->id)
            ->update(['status' => 'approved']);

        ApprovalStep::whereHas('approval', fn ($q) => $q->where('approval_request_id', $request->id))
            ->update(['approver_id' => $approver->id, 'approved_at' => now(), 'action' => 'approved', 'comment' => $notes]);

        return $request->fresh();
    }

    public function reject(Invoice $invoice, User $rejectedBy, string $reason): ApprovalRequest
    {
        $request = $this->latestPendingRequest($invoice);

        $this->approvalService->rejectRequest($request, $rejectedBy, $reason);

        $invoice->update(['approval_status' => 'rejected']);

        InvoiceApproval::where('invoice_id', $invoice->id)
            ->where('approval_request_id', $request->id)
            ->update(['status' => 'rejected', 'rejection_reason' => $reason]);

        return $request->fresh();
    }

    protected function latestPendingRequest(Invoice $invoice): ApprovalRequest
    {
        return ApprovalRequest::where('approvable_type', Invoice::class)
            ->where('approvable_id', $invoice->id)
            ->latest()
            ->firstOrFail();
    }

    public function getApprovalChain(Invoice $invoice): array
    {
        $request = ApprovalRequest::where('approvable_type', Invoice::class)
            ->where('approvable_id', $invoice->id)
            ->latest()
            ->first();

        if (! $request) {
            return [];
        }

        return $request->actions()->orderBy('acted_at')->get()->map(fn ($action) => [
            'action' => $action->action,
            'approver' => User::find($action->approver_id)?->name,
            'comment' => $action->comment,
            'acted_at' => $action->acted_at,
        ])->all();
    }

    public function getPendingForUser(User $user): \Illuminate\Support\Collection
    {
        return $this->approvalService->getPendingApprovalsForUser($user)
            ->filter(fn (ApprovalRequest $r) => $r->approvable_type === Invoice::class);
    }

    public function getAnalytics(Carbon $start, Carbon $end): array
    {
        $approvals = InvoiceApproval::whereBetween('submitted_at', [$start, $end])->get();

        $totalSubmitted = $approvals->count();
        $approved = $approvals->where('status', 'approved')->count();
        $rejected = $approvals->where('status', 'rejected')->count();
        $pending = $approvals->where('status', 'pending')->count();

        return [
            'total_submitted' => $totalSubmitted,
            'approved_count' => $approved,
            'approved_pct' => $totalSubmitted > 0 ? ($approved / $totalSubmitted) * 100 : 0,
            'rejected_count' => $rejected,
            'pending_count' => $pending,
        ];
    }

    /**
     * Fixed: previously queried whereJsonContains('roles', $role), which
     * doesn't reflect how spatie/laravel-permission actually stores role
     * assignments (a pivot table, not a JSON column on users) — this never
     * matched anyone. Sending is still a no-op pending a real notification
     * class, matching this codebase's existing degrade-gracefully pattern.
     */
    private function notifyApprovers(Invoice $invoice, int $level): void
    {
        $role = self::LEVEL_ROLES[$level] ?? null;

        if (! $role) {
            return;
        }

        $approvers = User::role($role)->get();

        foreach ($approvers as $approver) {
            // Notification::send($approver, new InvoiceApprovalNotification($invoice, $level));
        }
    }
}
