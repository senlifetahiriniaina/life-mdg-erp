<?php

namespace Modules\Calendar\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\CalendarAttendee;
use Modules\Calendar\Models\CalendarEvent;

class CalendarAttendeeFactory extends Factory
{
    protected $model = CalendarAttendee::class;

    public function definition(): array
    {
        return [
            'event_id'     => CalendarEvent::factory(),
            'user_id'      => null,
            'email'        => fake()->unique()->safeEmail(),
            'name'         => fake()->name(),
            'status'       => fake()->randomElement(['accepted', 'declined', 'tentative', 'needs-action']),
            'is_organizer' => false,
        ];
    }
}
