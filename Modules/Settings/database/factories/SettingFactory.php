<?php

namespace Modules\Settings\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Settings\Models\Setting;

/**
 * Chantier 32.9 (14-layer deep audit): this factory was scaffold
 * boilerplate — `fake()->word()` on `tenant_id` (an FK-shaped column,
 * getting a random string), `is_public` (a boolean column, getting a
 * random string that always cast truthy regardless of intent), and no
 * `module`/`value_type` at all despite both being real, actively-used
 * `$fillable` columns. Rewritten to match the model's real shape. `key`
 * uses Str::random() rather than fake()->unique()->word() — a bare
 * fake()->word() risks colliding with the new
 * (tenant_id, module, key) unique index (Chantier 32.9's sibling fix)
 * across the finite Faker word pool on a test suite that creates many
 * settings; a random suffix guarantees no test-authored collision.
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => null,
            'module'      => fake()->word(),
            'key'         => fake()->word() . '_' . \Illuminate\Support\Str::random(8),
            'value'       => fake()->word(),
            'value_type'  => 'string',
            'description' => fake()->sentence(),
            'is_public'   => fake()->boolean(),
        ];
    }

    /** A setting flagged visible to non-admin callers. */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => ['is_public' => true]);
    }

    /** A global (tenant_id = null) setting, explicit for readability at call sites. */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => null]);
    }
}
