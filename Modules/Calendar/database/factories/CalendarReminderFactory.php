<?php

namespace Modules\Calendar\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Models\CalendarReminder;

class CalendarReminderFactory extends Factory
{
    protected $model = CalendarReminder::class;

    public function definition(): array
    {
        return [
            'event_id'      => CalendarEvent::factory(),
            'user_id'       => null,
            'minutes_before'=> fake()->randomElement([5, 10, 15, 30, 60]),
            'method'        => fake()->randomElement(['email', 'popup', 'sms']),
            'sent_at'       => null,
        ];
    }
}
