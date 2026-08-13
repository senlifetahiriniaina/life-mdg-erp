<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Contact;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->optional(0.6)->phoneNumber(),
            'job_title' => fake()->optional(0.7)->jobTitle(),
            'department' => fake()->optional(0.5)->randomElement([
                'Sales', 'Marketing', 'Engineering', 'Finance', 'Operations', 'HR',
            ]),
            'status' => fake()->randomElement(['active', 'inactive', 'prospect']),
            'source' => fake()->randomElement([
                'website', 'referral', 'cold_call', 'event', 'social_media', 'other',
            ]),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function withAccount(int $accountId): static
    {
        return $this->state(['account_id' => $accountId]);
    }
}
