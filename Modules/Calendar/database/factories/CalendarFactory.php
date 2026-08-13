<?php

namespace Modules\Calendar\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\Calendar;

class CalendarFactory extends Factory
{
    protected $model = Calendar::class;

    public function definition(): array
    {
        return [
            'tenant_id'            => null,
            'user_id'              => User::factory(),
            'name'                 => fake()->words(3, true),
            'color'                => fake()->hexColor(),
            'type'                 => fake()->randomElement(['personal', 'shared', 'module']),
            'source'               => 'local',
            'is_primary'           => false,
            'is_visible'           => true,
            'sync_token'           => null,
            'external_calendar_id' => null,
        ];
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }
}
