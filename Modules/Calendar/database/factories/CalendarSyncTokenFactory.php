<?php

namespace Modules\Calendar\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Calendar\Models\CalendarSyncToken;

class CalendarSyncTokenFactory extends Factory
{
    protected $model = CalendarSyncToken::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => null,
            'user_id'          => User::factory(),
            'provider'         => fake()->randomElement(['google', 'outlook', 'apple']),
            'access_token'     => fake()->sha256(),
            'refresh_token'    => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'calendar_ids'     => null,
            'last_synced_at'   => null,
            'sync_errors'      => null,
        ];
    }
}
