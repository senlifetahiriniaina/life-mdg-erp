<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\Report;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Report',
            'type' => fake()->randomElement(['table', 'bar', 'line', 'pie']),
            'is_scheduled' => false,
        ];
    }
}
