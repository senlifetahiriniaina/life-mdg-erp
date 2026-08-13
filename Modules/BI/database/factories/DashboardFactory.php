<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\Dashboard;

/** @extends Factory<Dashboard> */
class DashboardFactory extends Factory
{
    protected $model = Dashboard::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Dashboard',
            'is_public' => false,
            'is_default' => false,
        ];
    }
}
