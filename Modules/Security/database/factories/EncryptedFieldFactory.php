<?php

namespace Modules\Security\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\EncryptedField;
use Modules\Security\Models\EncryptionKey;

class EncryptedFieldFactory extends Factory
{
    protected $model = EncryptedField::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'table_name' => fake()->randomElement(['users', 'contacts', 'employees', 'customers']),
            'column_name' => fake()->randomElement(['email', 'phone', 'ssn', 'national_id']),
            'encryption_algorithm' => fake()->randomElement(['AES-256-GCM', 'RSA']),
            'encryption_key_id' => EncryptionKey::factory(),
            'is_searchable' => fake()->boolean(),
            'metadata' => ['classification' => fake()->randomElement(['low', 'medium', 'high'])],
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        // No is_active column on this model; kept as a no-op state so
        // existing callers of ->inactive() don't break.
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        // No archived_at column on this model; kept as a no-op state so
        // existing callers of ->archived() don't break.
        return $this->state(fn (array $attributes) => []);
    }
}
