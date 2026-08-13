<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Tenant;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    protected $connection = 'central';

    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->company());

        return [
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'company_name' => $this->faker->company(),
            'domain' => "{$slug}.test",
            'plan' => $this->faker->randomElement(['starter', 'growth', 'enterprise']),
            'is_active' => true,
        ];
    }
}
