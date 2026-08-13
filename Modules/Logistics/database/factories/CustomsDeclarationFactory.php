<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\CustomsDeclaration;

/** @extends Factory<CustomsDeclaration> */
class CustomsDeclarationFactory extends Factory
{
    protected $model = CustomsDeclaration::class;

    public function definition(): array
    {
        $date = now()->format('Ymd');
        $seq = fake()->unique()->numberBetween(1, 9999);

        return [
            'reference' => sprintf('CST-%s-%04d', $date, $seq),
            'shipment_id' => null,
            'type' => 'export',
            'status' => 'draft',
            'country_export' => strtoupper(fake()->countryCode()),
            'country_import' => strtoupper(fake()->countryCode()),
            'incoterm' => fake()->optional()->randomElement(['EXW', 'FOB', 'CIF', 'DDP', 'DAP']),
            'total_declared_value' => fake()->randomFloat(2, 100, 50000),
            'currency' => 'USD',
            'total_duties' => null,
            'total_taxes' => null,
            'customs_broker' => fake()->optional()->company(),
            'mrn_number' => null,
            'submitted_at' => null,
            'cleared_at' => null,
            'rejection_reason' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
            'mrn_number' => strtoupper(fake()->bothify('MRN##??####??###')),
        ]);
    }

    public function cleared(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cleared',
            'submitted_at' => now()->subDays(3),
            'cleared_at' => now(),
            'mrn_number' => strtoupper(fake()->bothify('MRN##??####??###')),
            'total_duties' => fake()->randomFloat(2, 0, 5000),
            'total_taxes' => fake()->randomFloat(2, 0, 2000),
        ]);
    }

    public function export(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'export']);
    }

    public function import(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'import']);
    }
}
