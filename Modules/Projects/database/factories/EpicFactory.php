<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Epic;

/** @extends Factory<Epic> */
class EpicFactory extends Factory
{
    protected $model = Epic::class;

    public function definition(): array
    {
        return [
            'project_id' => ProjectFactory::new(),
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->hexColor(),
            'status' => fake()->randomElement(['open', 'in_progress', 'done']),
            'start_date' => fake()->optional()->dateTimeBetween('-1 month', '+1 month'),
            'end_date' => fake()->optional()->dateTimeBetween('+1 month', '+3 months'),
        ];
    }
}
