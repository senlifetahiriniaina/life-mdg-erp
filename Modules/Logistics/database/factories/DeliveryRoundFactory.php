<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\DeliveryRound;

/** @extends Factory<DeliveryRound> */
class DeliveryRoundFactory extends Factory
{
    protected $model = DeliveryRound::class;

    public function definition(): array
    {
        $date = now()->format('Ymd');
        $seq = fake()->unique()->numberBetween(1, 999);

        return [
            'reference' => sprintf('RND-%s-%03d', $date, $seq),
            'driver_name' => fake()->name(),
            'driver_phone' => fake()->phoneNumber(),
            'vehicle_plate' => strtoupper(fake()->bothify('??-###-??')),
            'vehicle_type' => fake()->randomElement(['van', 'truck', 'motorcycle', 'bicycle', 'other']),
            'carrier_id' => null,
            'status' => 'planned',
            'planned_date' => fake()->dateTimeBetween('now', '+7 days')->format('Y-m-d'),
            'started_at' => null,
            'completed_at' => null,
            'total_stops' => 0,
            'total_distance_km' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'started_at' => now()->subHours(2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subHours(8),
            'completed_at' => now()->subHour(),
            'total_distance_km' => fake()->randomFloat(2, 50, 300),
        ]);
    }
}
