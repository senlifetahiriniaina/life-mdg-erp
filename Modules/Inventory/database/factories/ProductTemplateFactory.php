<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Unit;

class ProductTemplateFactory extends Factory
{
    protected $model = ProductTemplate::class;

    public function definition(): array
    {
        return [
            'code' => 'tpl-' . uniqid(),
            'name' => $this->faker->words(2, true),
            'family' => $this->faker->randomElement(array_keys(ProductTemplate::FAMILIES)),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'description' => null,
            'default_attributes' => null,
            'is_active' => true,
        ];
    }
}
