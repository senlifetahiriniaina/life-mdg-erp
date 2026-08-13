<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\Forum;

/** @extends Factory<Forum> */
class ForumFactory extends Factory
{
    protected $model = Forum::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'category'    => $this->faker->randomElement(['general', 'billing', 'technical', 'feature-request']),
            'is_public'   => true,
            'sort_order'  => $this->faker->numberBetween(0, 10),
        ];
    }
}
