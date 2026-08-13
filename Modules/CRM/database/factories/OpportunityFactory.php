<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Pipeline;

class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'account_id' => null,
            'contact_id' => null,
            'owner_id' => null,
            'name' => fake()->company().' Deal',
            'stage' => fake()->randomElement(['prospecting', 'qualification', 'proposal', 'negotiation']),
            'probability' => fake()->numberBetween(10, 90),
            'amount' => fake()->randomFloat(2, 5000, 200000),
            'currency' => 'USD',
            'expected_close_date' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'status' => 'open',
        ];
    }
}
