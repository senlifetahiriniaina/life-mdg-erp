<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Opportunity;

/**
 * Manages the lead/opportunity status lifecycle and records every transition.
 */
class LeadStatusService
{
    public const VALID_STATUSES = ['new', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    /**
     * Allowed forward transitions per status. 'won' and 'lost' are terminal.
     */
    public const STATUS_WORKFLOW = [
        'new' => ['qualified', 'lost'],
        'qualified' => ['proposal', 'lost'],
        'proposal' => ['negotiation', 'lost'],
        'negotiation' => ['won', 'lost'],
        'won' => [],
        'lost' => [],
    ];

    /**
     * Transition an opportunity to a new status, recording the change.
     *
     * @throws \InvalidArgumentException when the target status is not recognised
     * @throws \Exception when the transition is not allowed by the workflow
     */
    public function transition(
        Opportunity $opportunity,
        string $newStatus,
        ?string $reason = null,
        array $metadata = []
    ): bool {
        if (! in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }

        $fromStatus = $opportunity->status;
        $allowed = self::STATUS_WORKFLOW[$fromStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw new \Exception("Cannot transition from {$fromStatus} to {$newStatus}");
        }

        $opportunity->status = $newStatus;
        $opportunity->save();

        DB::table('crm_lead_status_logs')->insert([
            'opportunity_id' => $opportunity->id,
            'from_status' => $fromStatus,
            'to_status' => $newStatus,
            'reason' => $reason,
            'metadata' => $metadata !== [] ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }
}
