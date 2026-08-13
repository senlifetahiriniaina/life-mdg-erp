<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\ServerConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerConfig>
 */
class ServerConfigFactory extends Factory
{
    protected $model = ServerConfig::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(2, true) . ' server',
            'provider'      => $this->faker->randomElement(['gcp', 'aws', 'azure', 'digitalocean', 'hetzner', 'ovh', 'custom']),
            'region'        => $this->faker->randomElement(['us-east-1', 'eu-west-1', 'ap-southeast-1']),
            'instance_type' => $this->faker->randomElement(['t3.micro', 'e2-medium', 'Standard_B2s']),
            'ip_address'    => $this->faker->ipv4(),
            'status'        => $this->faker->randomElement(['active', 'stopped', 'maintenance', 'unknown']),
            'metadata'      => null,
            'last_ping_at'  => null,
        ];
    }
}
