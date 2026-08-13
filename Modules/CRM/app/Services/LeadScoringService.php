<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Modules\CRM\Models\Lead;

class LeadScoringService
{
    public function recalculate(Lead $lead): int
    {
        $score = 0;

        // Contact linked
        if ($lead->contact_id) {
            $score += 10;
        }

        // Description filled
        if ($lead->description) {
            $score += 5;
        }

        // Status stage bonus
        $score += match ($lead->status) {
            'new' => 0,
            'contacted' => 10,
            'qualified' => 25,
            'proposal' => 35,
            'negotiation' => 45,
            'won' => 50,
            default => 0,
        };

        // Value bonus
        if ($lead->estimated_value !== null) {
            $value = (float) $lead->estimated_value;
            $score += match (true) {
                $value >= 100000 => 20,
                $value >= 10000 => 15,
                $value >= 1000 => 10,
                $value > 0 => 5,
                default => 0,
            };
        }

        // Activity bonus — number of activities linked
        $activityCount = $lead->activities()->count();
        $score += min(20, $activityCount * 2);

        $score = min(100, $score);

        $lead->updateQuietly(['score' => $score]);

        return $score;
    }
}
