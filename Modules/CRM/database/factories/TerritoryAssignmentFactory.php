<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Territory;
use Modules\CRM\Models\TerritoryAssignment;

class TerritoryAssignmentFactory extends Factory
{
    protected $model = TerritoryAssignment::class;

    public function definition(): array
    {
        return [
            'territory_id' => Territory::factory(),
            'contact_id' => null,
            'account_id' => null,
            'auto_assigned' => false,
        ];
    }
}
