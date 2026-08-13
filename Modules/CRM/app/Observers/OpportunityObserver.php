<?php

declare(strict_types=1);

namespace Modules\CRM\Observers;

use App\Events\CrmOpportunityUpdated;
use Illuminate\Support\Facades\Auth;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityHistory;

class OpportunityObserver
{
    /** @var array<int, string> */
    private static array $track = ['stage_id', 'status', 'amount', 'expected_close_date', 'owner_id'];

    public function created(Opportunity $opportunity): void
    {
        CrmOpportunityUpdated::dispatch($opportunity, 'created');
    }

    public function updating(Opportunity $opportunity): void
    {
        $dirty = $opportunity->getDirty();

        foreach (self::$track as $field) {
            if (! array_key_exists($field, $dirty)) {
                continue;
            }

            OpportunityHistory::create([
                'opportunity_id' => $opportunity->id,
                'field' => $field,
                'old_value' => (string) ($opportunity->getOriginal($field) ?? ''),
                'new_value' => (string) ($dirty[$field] ?? ''),
                'changed_by' => Auth::id(),
                'changed_at' => now(),
            ]);
        }
    }

    public function updated(Opportunity $opportunity): void
    {
        CrmOpportunityUpdated::dispatch($opportunity, 'updated');
    }
}
