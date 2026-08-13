<?php

declare(strict_types=1);

namespace Modules\Achats\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderApproval;

/**
 * Multi-level approval chain for Achats purchase orders.
 *
 * Approval levels:
 *   supervisor — required for all POs
 *   manager    — required when total_amount > 5 000
 *   finance    — required when total_amount > 10 000
 */
class PurchaseApprovalChainService
{
    private const MANAGER_THRESHOLD = 5_000;
    private const FINANCE_THRESHOLD = 10_000;

    /**
     * Create the approval chain for a purchase order.
     *
     * @param array<int> $approverIds  Optional explicit approver IDs (in level order).
     *                                 When empty, the default hierarchy is resolved.
     */
    public function createApprovalChain(PurchaseOrder $purchaseOrder, array $approverIds = []): bool
    {
        try {
            return DB::transaction(function () use ($purchaseOrder, $approverIds) {
                if (empty($approverIds)) {
                    $approverIds = $this->getDefaultApprovalChain($purchaseOrder);
                }

                $approvalLevels = ['supervisor', 'manager', 'finance'];

                foreach ($approverIds as $index => $approverId) {
                    PurchaseOrderApproval::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'approver_id'       => $approverId,
                        'approval_level'    => $approvalLevels[$index] ?? 'supervisor',
                        'status'            => 'pending',
                    ]);
                }

                // Mark PO as pending approval
                $purchaseOrder->update(['status' => 'submitted']);

                Log::info('Approval chain created', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'approvers_count'   => count($approverIds),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to create approval chain', [
                'purchase_order_id' => $purchaseOrder->id,
                'error'             => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Resolve default approvers based on PO amount and role hierarchy.
     *
     * @return array<int>
     */
    private function getDefaultApprovalChain(PurchaseOrder $purchaseOrder): array
    {
        $supervisorRole = DB::table('roles')->where('name', 'manager')->first();
        $managerRole    = DB::table('roles')->where('name', 'admin')->first();
        $financeRole    = DB::table('roles')->where('name', 'accountant')->first();

        $approvers = [];

        // Supervisor level — always required
        if ($supervisorRole) {
            $supervisor = User::whereHas('roles', fn ($q) => $q->where('id', $supervisorRole->id))->first();
            if ($supervisor) {
                $approvers[] = $supervisor->id;
            }
        }

        // Manager level — required when total > 5 000
        $total = (float) $purchaseOrder->total;
        if ($total > self::MANAGER_THRESHOLD && $managerRole) {
            $manager = User::whereHas('roles', fn ($q) => $q->where('id', $managerRole->id))->first();
            if ($manager) {
                $approvers[] = $manager->id;
            }
        }

        // Finance level — required when total > 10 000
        if ($total > self::FINANCE_THRESHOLD && $financeRole) {
            $finance = User::whereHas('roles', fn ($q) => $q->where('id', $financeRole->id))->first();
            if ($finance) {
                $approvers[] = $finance->id;
            }
        }

        return $approvers;
    }

    /**
     * Approve the purchase order at the current pending level for the given user.
     */
    public function approvePurchaseOrder(
        PurchaseOrder $purchaseOrder,
        User $approver,
        ?string $comments = null
    ): bool {
        try {
            return DB::transaction(function () use ($purchaseOrder, $approver, $comments) {
                $approval = PurchaseOrderApproval::where('purchase_order_id', $purchaseOrder->id)
                    ->where('approver_id', $approver->id)
                    ->where('status', 'pending')
                    ->first();

                if (! $approval) {
                    throw new \RuntimeException('No pending approval found for this user');
                }

                if ($comments) {
                    $approval->update(['notes' => $comments]);
                }

                $approval->approve();

                // Check whether all levels have been approved
                $pendingCount = PurchaseOrderApproval::where('purchase_order_id', $purchaseOrder->id)
                    ->where('status', 'pending')
                    ->count();

                if ($pendingCount === 0) {
                    $purchaseOrder->update(['status' => 'approved']);
                }

                Log::info('Purchase order approved', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'approved_by'       => $approver->id,
                    'approval_level'    => $approval->approval_level,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to approve purchase order', [
                'purchase_order_id' => $purchaseOrder->id,
                'approver_id'       => $approver->id,
                'error'             => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reject the purchase order, cascading the rejection to all remaining pending approvals.
     */
    public function rejectPurchaseOrder(
        PurchaseOrder $purchaseOrder,
        User $approver,
        string $rejectionReason
    ): bool {
        try {
            return DB::transaction(function () use ($purchaseOrder, $approver, $rejectionReason) {
                $approval = PurchaseOrderApproval::where('purchase_order_id', $purchaseOrder->id)
                    ->where('approver_id', $approver->id)
                    ->where('status', 'pending')
                    ->first();

                if (! $approval) {
                    throw new \RuntimeException('No pending approval found for this user');
                }

                $approval->reject($rejectionReason);

                // Cascade — cancel all remaining pending approvals in the chain
                PurchaseOrderApproval::where('purchase_order_id', $purchaseOrder->id)
                    ->where('status', 'pending')
                    ->update([
                        'status'           => 'rejected',
                        'rejection_reason' => 'Rejected due to upstream rejection',
                    ]);

                // Reset PO to draft
                $purchaseOrder->update(['status' => 'draft']);

                Log::warning('Purchase order rejected', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'rejected_by'       => $approver->id,
                    'reason'            => $rejectionReason,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to reject purchase order', [
                'purchase_order_id' => $purchaseOrder->id,
                'approver_id'       => $approver->id,
                'error'             => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get all pending approvals assigned to the given user.
     *
     * @return Collection<int, PurchaseOrderApproval>
     */
    public function getPendingApprovalsForUser(User $user): Collection
    {
        return PurchaseOrderApproval::where('approver_id', $user->id)
            ->where('status', 'pending')
            ->with('purchaseOrder.supplier', 'approver')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Return the full approval chain for a purchase order.
     *
     * @return Collection<int, PurchaseOrderApproval>
     */
    public function getApprovalChain(PurchaseOrder $purchaseOrder): Collection
    {
        return PurchaseOrderApproval::where('purchase_order_id', $purchaseOrder->id)
            ->with('approver')
            ->orderBy('approval_level')
            ->get();
    }

    /**
     * Check whether the given user currently has authority to approve the PO.
     *
     * Rules:
     * - User must have a pending approval record for the PO.
     * - All approvals at lower ordinal levels must already be approved.
     */
    public function canApprove(PurchaseOrder $purchaseOrder, User $user): bool
    {
        $approval = PurchaseOrderApproval::where('purchase_order_id', $purchaseOrder->id)
            ->where('approver_id', $user->id)
            ->first();

        if (! $approval || $approval->status !== 'pending') {
            return false;
        }

        // Map levels to ordinal positions for ordering checks
        $levelOrder = ['supervisor' => 0, 'manager' => 1, 'finance' => 2];
        $currentOrdinal = $levelOrder[$approval->approval_level] ?? 0;

        // Fetch all approvals for this PO
        $chain = PurchaseOrderApproval::where('purchase_order_id', $purchaseOrder->id)->get();

        foreach ($chain as $record) {
            $ordinal = $levelOrder[$record->approval_level] ?? 0;
            if ($ordinal < $currentOrdinal && $record->status !== 'approved') {
                return false;
            }
        }

        return true;
    }

    /**
     * Return per-PO approval statistics.
     *
     * @return array<string, mixed>
     */
    public function getApprovalStats(PurchaseOrder $purchaseOrder): array
    {
        $approvals = PurchaseOrderApproval::where('purchase_order_id', $purchaseOrder->id)->get();

        $total    = $approvals->count();
        $approved = $approvals->where('status', 'approved')->count();
        $pending  = $approvals->where('status', 'pending')->count();
        $rejected = $approvals->where('status', 'rejected')->count();

        return [
            'total_approvals' => $total,
            'approved'        => $approved,
            'pending'         => $pending,
            'rejected'        => $rejected,
            'progress'        => $total > 0 ? ($approved / $total) * 100 : 0,
        ];
    }
}
