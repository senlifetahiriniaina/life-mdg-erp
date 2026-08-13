<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\Ticket;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;
        return [
            'ticket_number' => 'HD-' . str_pad((string) ($counter + rand(1000, 9999)), 6, '0', STR_PAD_LEFT),
            'reporter_id' => User::factory(),
            'subject' => fake()->sentence(),
            'channel' => 'web',
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => 'open',
            'sla_breached' => false,
        ];
    }
}
