<?php

declare(strict_types=1);

namespace Modules\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\RecurringOrderTemplate;

class RecurringOrderTemplateFactory extends Factory
{
    protected $model = RecurringOrderTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'reference' => 'REC-' . now()->format('Y') . '-' . strtoupper(uniqid()),
            'name' => $this->faker->company() . ' — commande mensuelle',
            'currency' => 'MGA',
            'recurrence' => 'monthly',
            'next_run_at' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
