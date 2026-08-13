<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AuditLog;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'action' => $this->faker->randomElement(['created', 'updated', 'deleted', 'login', 'logout']),
            'subject_type' => null,
            'subject_id' => null,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => now(),
        ];
    }

    public function forCreate(): static
    {
        return $this->state([
            'action' => 'created',
            'old_values' => null,
            'new_values' => ['name' => 'Test', 'email' => 'test@example.com'],
        ]);
    }

    public function forUpdate(): static
    {
        return $this->state([
            'action' => 'updated',
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
        ]);
    }

    public function forDelete(): static
    {
        return $this->state([
            'action' => 'deleted',
            'old_values' => ['name' => 'Deleted Item'],
            'new_values' => null,
        ]);
    }
}
