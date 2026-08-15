<?php

namespace Modules\Security\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\AuthenticationEvent;

class AuthenticationEventFactory extends Factory
{
    protected $model = AuthenticationEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_email' => fake()->safeEmail(),
            'event_type' => fake()->randomElement(['login', 'logout', 'failed_login']),
            'authentication_method' => fake()->randomElement(['password', 'hardware_key']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_info' => [
                'os' => fake()->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
                'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari']),
            ],
            'status' => fake()->randomElement(['success', 'failure', 'blocked']),
            'failure_reason' => fake()->randomElement(['invalid_password', 'account_locked', 'expired_credentials']),
            'trust_score' => fake()->randomFloat(2, 0, 100),
            'risk_factors' => fake()->randomElements(['unusual_location', 'new_device', 'vpn_detected', 'impossible_travel'], 2),
            'authenticated_at' => fake()->dateTime(),
            'created_at' => fake()->dateTime(),
            // NOTE: 'updated_at' is intentionally omitted — the model declares it
            // in $fillable/$casts, but security_authentication_events (see
            // 2026_06_07_000004_create_security_authentication_events_table.php)
            // never got an updated_at column ($timestamps = false on the model,
            // only 'created_at' is a real column). Setting it here would insert
            // against a column that doesn't exist.
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        // No archived_at column on this model; kept as a no-op state so
        // existing callers of ->archived() don't break.
        return $this->state(fn (array $attributes) => []);
    }
}
