<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\ScoringRule;

class ScoringRuleFactory extends Factory
{
    protected $model = ScoringRule::class;

    public function definition(): array
    {
        $category = $this->faker->randomElement(['engagement', 'fit', 'velocity', 'history']);
        $conditionField = $this->faker->randomElement(['deal_size', 'stage', 'activities_count', 'days_in_stage', 'email_opens']);
        $conditionOperator = $this->faker->randomElement(['gt', 'lt', 'gte', 'eq']);
        $conditionValue = (string) $this->faker->numberBetween(1, 100);

        return [
            'name' => $this->faker->words(3, true),
            'category' => $category,
            'condition_field' => $conditionField,
            'condition_operator' => $conditionOperator,
            'condition_value' => $conditionValue,
            'points' => $this->faker->numberBetween(5, 25),
            'weight' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
