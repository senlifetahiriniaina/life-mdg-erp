<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Journal;

/** @extends Factory<Journal> */
class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('??##')),
            'type' => fake()->randomElement(['sale', 'purchase', 'cash', 'bank', 'general']),
            'currency' => 'USD',
            'is_active' => true,
        ];
    }
}
