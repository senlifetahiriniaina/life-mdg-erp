<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\GdprConsent;

class GdprConsentFactory extends Factory
{
    protected $model = GdprConsent::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'email' => $this->faker->safeEmail(),
            'consent_type' => $this->faker->randomElement(['marketing', 'analytics', 'functional', 'necessary']),
            'granted' => false,
            'granted_at' => null,
            'revoked_at' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'source' => 'web_form',
        ];
    }

    public function granted(): static
    {
        return $this->state([
            'granted' => true,
            'granted_at' => now(),
            'revoked_at' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state([
            'granted' => false,
            'revoked_at' => now(),
        ]);
    }
}
