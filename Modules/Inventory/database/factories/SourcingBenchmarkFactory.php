<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\SourcingBenchmark;

class SourcingBenchmarkFactory extends Factory
{
    protected $model = SourcingBenchmark::class;

    public function definition(): array
    {
        return [
            'product_id' => null,
            'product_template_id' => null,
            'material_label' => $this->faker->words(2, true),
            'source' => $this->faker->randomElement(array_keys(SourcingBenchmark::SOURCES)),
            'source_name_other' => null,
            'source_url' => null,
            'unit_price' => $this->faker->randomFloat(2, 1, 50),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'CNY']),
            'unit' => $this->faker->randomElement(['m', 'kg', 'piece']),
            'quantity_reference' => null,
            'observed_at' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
