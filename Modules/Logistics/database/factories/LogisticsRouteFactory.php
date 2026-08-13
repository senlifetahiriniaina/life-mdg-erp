<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\LogisticsRoute;

/** @extends Factory<LogisticsRoute> */
class LogisticsRouteFactory extends Factory
{
    protected $model = LogisticsRoute::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city().' → '.fake()->city(),
            'code' => strtoupper(fake()->unique()->bothify('RT-####')),
            'origin_name' => fake()->city(),
            'origin_country' => fake()->countryCode(),
            'destination_name' => fake()->city(),
            'destination_country' => fake()->countryCode(),
            'mode' => fake()->randomElement(['road', 'air', 'sea', 'rail', 'multimodal']),
            'distance_km' => fake()->numberBetween(100, 15000),
            'estimated_transit_days' => fake()->numberBetween(1, 30),
            'is_active' => true,
        ];
    }
}
