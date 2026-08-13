<?php

namespace Modules\Calendar\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;

class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 month');
        $end   = (clone $start)->modify('+' . fake()->numberBetween(30, 180) . ' minutes');

        return [
            'tenant_id'                  => null,
            'calendar_id'                => Calendar::factory(),
            'title'                      => fake()->sentence(4),
            'description'                => fake()->optional()->paragraph(),
            'start_at'                   => $start,
            'end_at'                     => $end,
            'all_day'                    => false,
            'location'                   => fake()->optional()->address(),
            'url'                        => null,
            'recurrence_rule'            => null,
            'recurrence_exception_dates' => null,
            'status'                     => 'confirmed',
            'visibility'                 => 'public',
            'source'                     => 'local',
            'external_event_id'          => null,
            'external_etag'              => null,
            'module_type'                => null,
            'module_id'                  => null,
            'color'                      => null,
            'created_by'                 => User::factory(),
        ];
    }

    public function allDay(): static
    {
        return $this->state(['all_day' => true]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
