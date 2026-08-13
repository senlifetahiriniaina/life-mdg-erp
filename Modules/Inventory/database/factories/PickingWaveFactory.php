<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\PickingWave;

/** @extends Factory<PickingWave> */
class PickingWaveFactory extends Factory
{
    protected $model = PickingWave::class;

    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(['open', 'in_progress', 'completed']),
            'picker_id' => null,
            'order_ids' => [fake()->numberBetween(1, 100), fake()->numberBetween(101, 200)],
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'started_at' => now()->subHour(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);
    }
}
