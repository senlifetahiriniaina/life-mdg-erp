<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityScore;

class OpportunityScoreFactory extends Factory
{
    protected $model = OpportunityScore::class;

    public function definition(): array
    {
        $totalScore = $this->faker->numberBetween(0, 100);

        $grade = match (true) {
            $totalScore >= 80 => 'A',
            $totalScore >= 65 => 'B',
            $totalScore >= 50 => 'C',
            $totalScore >= 35 => 'D',
            default => 'F',
        };

        $engagementScore = $this->faker->numberBetween(0, 30);
        $fitScore = $this->faker->numberBetween(0, 30);
        $velocityScore = $this->faker->numberBetween(0, 30);
        $historyScore = $this->faker->numberBetween(0, 30);

        return [
            'opportunity_id' => Opportunity::factory(),
            'total_score' => $totalScore,
            'grade' => $grade,
            'engagement_score' => $engagementScore,
            'fit_score' => $fitScore,
            'velocity_score' => $velocityScore,
            'history_score' => $historyScore,
            'win_probability' => round($totalScore * 0.01, 2),
            'score_breakdown' => [
                'engagement' => $engagementScore,
                'fit' => $fitScore,
                'velocity' => $velocityScore,
                'history' => $historyScore,
            ],
            'signals_used' => [],
            'scored_at' => now(),
        ];
    }
}
