<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\ResourceCapacity;

/** @extends Factory<ResourceCapacity> */
class ResourceCapacityFactory extends Factory
{
    protected $model = ResourceCapacity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'available_hours' => 8.0,
            'is_holiday' => false,
            'is_leave' => false,
            'notes' => null,
        ];
    }
}
