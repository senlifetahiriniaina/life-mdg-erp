<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Carrier;

/** @extends Factory<Carrier> */
class CarrierFactory extends Factory
{
    protected $model = Carrier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Logistics',
            'code' => strtoupper(fake()->unique()->bothify('??##')),
            'type' => fake()->randomElement(['road', 'air', 'sea', 'rail', 'multimodal']),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'country' => fake()->countryCode(),
            'is_active' => true,
            'rating' => fake()->randomFloat(2, 3.0, 5.0),
        ];
    }
}
