<?php

declare(strict_types=1);

namespace Modules\CRM\Observers;

use Modules\CRM\Models\Lead;
use Modules\CRM\Services\LeadScoringService;

class LeadObserver
{
    public function __construct(private readonly LeadScoringService $scoring) {}

    public function saved(Lead $lead): void
    {
        if ($lead->wasChanged(['status', 'estimated_value', 'email', 'phone', 'company'])) {
            $this->scoring->recalculate($lead);
        }
    }
}
