<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Helpdesk\Models\KbCategory;

class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'name' => ucfirst((string) $name),
            'slug' => Str::slug((string) $name).'-'.$this->faker->unique()->randomNumber(4),
            'description' => $this->faker->optional()->sentence(),
            'parent_id' => null,
            'created_by' => User::factory(),
            'icon' => $this->faker->optional()->word(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
