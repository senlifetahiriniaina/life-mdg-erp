<?php

namespace Modules\Validation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\ValidationRule;

class ValidationRuleFactory extends Factory
{
    protected $model = ValidationRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'field' => fake()->randomElement(['email', 'phone', 'name', 'address']),
            'type' => 'required',
            'params' => [],
            'message' => null,
        ];
    }
}
