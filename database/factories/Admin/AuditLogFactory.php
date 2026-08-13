<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id'       => null,
            'action'        => $this->faker->randomElement(['create', 'update', 'delete', 'login', 'logout', 'security']),
            'resource_type' => $this->faker->randomElement(['User', 'ServerConfig', 'Backup', null]),
            'resource_id'   => $this->faker->numberBetween(1, 100),
            'ip_address'    => $this->faker->ipv4(),
            'user_agent'    => $this->faker->userAgent(),
            'payload'       => null,
        ];
    }
}
