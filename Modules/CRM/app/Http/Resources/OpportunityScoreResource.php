<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'total_score' => $this->total_score,
            'grade' => $this->grade,
            'engagement_score' => $this->engagement_score,
            'fit_score' => $this->fit_score,
            'velocity_score' => $this->velocity_score,
            'history_score' => $this->history_score,
            'win_probability' => (float) $this->win_probability,
            'score_breakdown' => $this->score_breakdown,
            'signals_used' => $this->signals_used,
            'scored_at' => $this->scored_at?->toIso8601String(),
            'action' => $this->getRecommendedAction(),
            'is_high_value' => $this->isHighValue(),
            'opportunity' => $this->whenLoaded('opportunity', fn () => [
                'id' => $this->opportunity->id,
                'name' => $this->opportunity->name,
                'stage' => $this->opportunity->stage,
                'amount' => $this->opportunity->amount,
            ]),
        ];
    }
}
