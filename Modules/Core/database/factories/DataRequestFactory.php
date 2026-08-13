<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\DataRequest;

class DataRequestFactory extends Factory
{
    protected $model = DataRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'email' => $this->faker->safeEmail(),
            'request_type' => $this->faker->randomElement(['export', 'deletion', 'rectification', 'access']),
            'status' => 'pending',
            'notes' => null,
            'admin_notes' => null,
            'requested_at' => now(),
            'completed_at' => null,
            'expires_at' => null,
            'data_snapshot' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => 'pending',
            'requested_at' => now()->subDays(31),
        ]);
    }
}
