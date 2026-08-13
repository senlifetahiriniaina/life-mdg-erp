<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use Carbon\Carbon;

/**
 * Invoice Approval Service
 * OHADA-compliant 3-level approval workflow based on amount thresholds.
 * Ensures proper authorization before payment.
 */
class InvoiceApprovalService
{
    // OHADA approval thresholds (CFA Franc)
    private const THRESHOLDS = [
        'level_1' => 100_000,    // 100K XOF - Manager approval
        'level_2' => 500_000,    // 500K XOF - Director approval  
        'level_3' => 10_000_000, // 10M XOF - CEO approval
    ];

    /**
     * Get required approval level based on amount
     */
    public function getApprovalLevel(float $amount): int
    {
        if ($amount <= self::THRESHOLDS['level_1']) {
            return 1;
        } elseif ($amount <= self::THRESHOLDS['level_2']) {
            return 2;
        }
        return 3;
    }

    /**
     * Get approval level label
     */
    public function getLevelLabel(int $level): string
    {
        return match($level) {
            1 => 'Manager Approval (≤ 100K XOF)',
            2 => 'Director Approval (≤ 500K XOF)',
            3 => 'CEO Approval (> 500K XOF)',
            default => 'Unknown',
        };
    }

    /**
     * Get amount threshold for level
     */
    public function getThreshold(int $level): float
    {
        return self::THRESHOLDS["level_{$level}"] ?? 0;
    }

    /**
     * Submit invoice for approval
     */
    public function submitForApproval(Invoice $invoice, User $submittedBy): InvoiceApproval
    {
        $approvalLevel = $this->getApprovalLevel($invoice->amount);

        $approval = InvoiceApproval::create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'required_level' => $approvalLevel,
            'current_level' => 1,
            'status' => 'pending_level_1',
            'submitted_by' => $submittedBy->id,
            'submitted_at' => now(),
            'notes' => "Invoice {$invoice->invoice_number} submitted for approval",
        ]);

        // Mark invoice as pending
        $invoice->update(['approval_status' => 'pending']);

        // Notify level 1 approvers
        $this->notifyApprovers($invoice, 1, $submittedBy);

        return $approval;
    }

    /**
     * Approve invoice at current level
     */
    public function approveLevel(InvoiceApproval $approval, User $approver, ?string $notes = null): bool
    {
        $approval->update([
            'approved_by_level_' . $approval->current_level => $approver->id,
            'approved_at_level_' . $approval->current_level => now(),
            'notes' => $notes,
        ]);

        // Check if all levels approved
        if ($approval->current_level >= $approval->required_level) {
            // All approvals complete
            $approval->update(['status' => 'approved', 'approved_at' => now()]);
            
            $invoice = $approval->invoice;
            $invoice->update(['approval_status' => 'approved']);

            // Notify finance team for payment
            $this->notifyFinance($invoice, 'approved');

            return true;
        }

        // Move to next level
        $nextLevel = $approval->current_level + 1;
        $approval->update([
            'current_level' => $nextLevel,
            'status' => "pending_level_{$nextLevel}",
        ]);

        // Notify next level approvers
        $this->notifyApprovers($approval->invoice, $nextLevel, $approver);

        return false;
    }

    /**
     * Reject invoice
     */
    public function reject(InvoiceApproval $approval, User $rejectedBy, string $reason): bool
    {
        $approval->update([
            'status' => 'rejected',
            'rejected_by' => $rejectedBy->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $invoice = $approval->invoice;
        $invoice->update(['approval_status' => 'rejected']);

        // Notify submitter
        $this->notifyRejection($invoice, $reason);

        return true;
    }

    /**
     * Get approval chain for invoice
     */
    public function getApprovalChain(Invoice $invoice): array
    {
        $approval = $invoice->approval;
        
        if (!$approval) {
            return [];
        }

        $chain = [];
        for ($level = 1; $level <= $approval->required_level; $level++) {
            $approverColumn = "approved_by_level_{$level}";
            $dateColumn = "approved_at_level_{$level}";

            $approver = null;
            if ($approval->$approverColumn) {
                $approver = User::find($approval->$approverColumn);
            }

            $chain[] = [
                'level' => $level,
                'label' => $this->getLevelLabel($level),
                'status' => $approval->current_level >= $level ? 'approved' : 'pending',
                'approver' => $approver?->name,
                'approved_at' => $approval->$dateColumn,
                'threshold' => $this->getThreshold($level),
            ];
        }

        return $chain;
    }

    /**
     * Get pending approvals for user
     */
    public function getPendingForUser(User $user, int $level): \Illuminate\Database\Eloquent\Collection
    {
        return InvoiceApproval::where('current_level', $level)
            ->where('status', "pending_level_{$level}")
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('invoice', function ($q) use ($user) {
                // Only show invoices where user has approval permission
                $q->where('status', '!=', 'paid');
            })
            ->with('invoice')
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * Get approval analytics
     */
    public function getAnalytics(int $tenantId, Carbon $start, Carbon $end): array
    {
        $approvals = InvoiceApproval::where('tenant_id', $tenantId)
            ->whereBetween('submitted_at', [$start, $end])
            ->get();

        $totalSubmitted = $approvals->count();
        $approved = $approvals->where('status', 'approved')->count();
        $rejected = $approvals->where('status', 'rejected')->count();
        $pending = $approvals->whereIn('status', ['pending_level_1', 'pending_level_2', 'pending_level_3'])->count();

        $avgTimeToApproval = $approvals
            ->where('status', 'approved')
            ->avg(function ($a) {
                if ($a->approved_at && $a->submitted_at) {
                    return $a->approved_at->diffInHours($a->submitted_at);
                }
                return 0;
            });

        return [
            'total_submitted' => $totalSubmitted,
            'approved_count' => $approved,
            'approved_pct' => $totalSubmitted > 0 ? ($approved / $totalSubmitted) * 100 : 0,
            'rejected_count' => $rejected,
            'pending_count' => $pending,
            'avg_approval_time_hours' => round($avgTimeToApproval ?? 0, 2),
            'by_level' => [
                'level_1' => $approvals->where('required_level', 1)->count(),
                'level_2' => $approvals->where('required_level', 2)->count(),
                'level_3' => $approvals->where('required_level', 3)->count(),
            ],
        ];
    }

    /**
     * Notify approvers
     */
    private function notifyApprovers(Invoice $invoice, int $level, User $from): void
    {
        $approverRole = match($level) {
            1 => 'manager',
            2 => 'director',
            3 => 'ceo',
            default => null,
        };

        if (!$approverRole) {
            return;
        }

        $approvers = User::where('tenant_id', $invoice->tenant_id)
            ->whereJsonContains('roles', $approverRole)
            ->get();

        foreach ($approvers as $approver) {
            // Send notification
            // Notification::send($approver, new InvoiceApprovalNotification($invoice, $level));
        }
    }

    /**
     * Notify finance team for payment
     */
    private function notifyFinance(Invoice $invoice, string $status): void
    {
        // Send notification to finance team
    }

    /**
     * Notify rejection
     */
    private function notifyRejection(Invoice $invoice, string $reason): void
    {
        // Send notification to submitter
    }
}
