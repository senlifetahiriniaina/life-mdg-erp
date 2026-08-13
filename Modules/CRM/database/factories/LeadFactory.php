<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Lead;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'status' => $this->faker->randomElement(['new', 'contacted', 'qualified', 'unqualified', 'converted']),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'source' => $this->faker->randomElement(['direct', 'organic', 'referral', 'cold_call', 'email', 'social', 'ads', 'event']),
        ];
    }
}
