<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\CallLog;

class CallLogFactory extends Factory
{
    protected $model = CallLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'direction' => $this->faker->randomElement(['inbound', 'outbound']),
            'status' => $this->faker->randomElement(['answered', 'missed', 'initiated']),
            'phone_number' => $this->faker->phoneNumber(),
            'called_at' => now(),
        ];
    }
}
