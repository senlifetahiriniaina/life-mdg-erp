<?php

namespace Modules\CRM\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Account;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'type' => fake()->randomElement(['Customer', 'Prospect', 'Partner', 'Vendor']),
            'industry' => fake()->randomElement(['Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing']),
            'website' => fake()->url(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'employee_count' => fake()->numberBetween(1, 1000),
            'annual_revenue' => fake()->numerify('###########'),
            'currency' => 'USD',
            'billing_address' => fake()->address(),
            'billing_city' => fake()->city(),
            'billing_country' => fake()->country(),
            'description' => fake()->paragraph(),
            'custom_fields' => [],
        ];
    }
}
