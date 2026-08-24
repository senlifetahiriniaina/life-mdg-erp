<?php

namespace Modules\AI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\AI\Models\AiUsageLimit;

/**
 * Chantier 32.2 (14-layer deep audit of Modules\AI), layer 5 (data format):
 * this was scaffold boilerplate — `fake()->word()` on the FK/decimal/boolean
 * columns `ai_usage_limits` actually has (real strings would fail sqlite's
 * type coercion on some drivers and are semantically meaningless anyway),
 * plus a dozen phantom columns (`name`/`title`/`description`/`slug`/
 * `status`/`code`/`email`/`phone`/`amount`/`quantity`/`price`/`cost`/
 * `is_active`/`notes`) that don't exist on this table at all — confirmed
 * empirically to fatal every real `AiUsageLimit::factory()->create()` call
 * with "table ai_usage_limits has no column named name". Rewritten to match
 * the model's real `$fillable` and the real `limit_type`/`period` enums
 * `AiActionAdvisorController::adminSetLimit()` actually validates against.
 */
class AiUsageLimitFactory extends Factory
{
    protected $model = AiUsageLimit::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => fake()->numberBetween(1, 1000),
            'user_id'         => null,
            'limit_type'      => fake()->randomElement(['usd', 'tokens']),
            'limit_value'     => fake()->randomFloat(4, 1, 500),
            'period'          => fake()->randomElement(['daily', 'weekly', 'monthly']),
            'block_on_exceed' => true,
            'active'          => true,
        ];
    }

    /** Scope the limit to a single user rather than the whole tenant. */
    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
