<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Campaign;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->sentence(3),
            'description'   => $this->faker->paragraph(),
            'type'          => $this->faker->randomElement(['email', 'sms', 'whatsapp', 'multi_channel']),
            'status'        => 'draft',
            'owner_id'      => 1,
            'target_count'  => $this->faker->numberBetween(100, 5000),
            'channels'      => ['email'],
            'segments'      => ['all'],
        ];
    }
}
