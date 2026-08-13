<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\Team;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Team',
            'email' => null,
            'auto_assignment' => false,
            'is_active' => true,
        ];
    }
}
