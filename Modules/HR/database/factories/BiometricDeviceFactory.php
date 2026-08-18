<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\BiometricDevice;

class BiometricDeviceFactory extends Factory
{
    protected $model = BiometricDevice::class;

    public function definition(): array
    {
        return [
            'device_id' => fake()->unique()->bothify('DEV-####'),
            'device_name' => fake()->words(2, true).' Terminal',
            'device_type' => fake()->randomElement(['fingerprint', 'facial_recognition', 'rfid', 'manual_pin']),
            'manufacturer' => fake()->company(),
            'model' => fake()->bothify('MX-###'),
            'location' => fake()->streetAddress(),
            'building' => fake()->randomElement(['Building A', 'Building B', 'Main Building']),
            'floor' => fake()->randomElement(['1', '2', '3', 'Ground']),
            'zone' => fake()->randomElement(['Entrance', 'Warehouse', 'Office']),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'ip_address' => fake()->ipv4(),
            'mac_address' => fake()->macAddress(),
            'status' => fake()->randomElement(['active', 'inactive']),
            'last_sync' => fake()->dateTimeBetween('-1 day', 'now'),
            'firmware_version' => fake()->numerify('#.#.#'),
            'capacity' => fake()->numberBetween(500, 5000),
        ];
    }
}
