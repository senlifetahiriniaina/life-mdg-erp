<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Territory;

class TerritoryFactory extends Factory
{
    protected $model = Territory::class;

    public function definition(): array
    {
        return [
            'assigned_to' => User::factory(),
            'name' => fake()->state().' Territory',
            'code' => fake()->unique()->stateAbbr(),
            'description' => fake()->sentence(),
            'region' => fake()->word(),
            'sales_target' => fake()->randomFloat(2, 100000, 500000),
            'currency' => 'USD',
            'is_active' => true,
            'year_start_date' => '2026-01-01',
            'parent_territory_id' => null,
        ];
    }
}
