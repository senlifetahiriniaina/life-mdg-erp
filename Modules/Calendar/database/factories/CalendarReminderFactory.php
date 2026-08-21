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
            // Chantier 32.12: 'sms' was never a real accepted value —
            // CalendarReminder's own docblock and StoreEvent's validation
            // (`reminders.*.method' => 'in:email,push,popup'`) both agree
            // the real vocabulary is email|push|popup.
            'method'        => fake()->randomElement(['email', 'push', 'popup']),
            'sent_at'       => null,
        ];
    }
}
