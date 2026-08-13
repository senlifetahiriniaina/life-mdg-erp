<?php

declare(strict_types=1);
use Modules\Inventory\Services\AI\InventoryAIService;


test('can classify products ABC', function () {
    $mock = Mockery::mock(InventoryAIService::class);
    $mock->shouldReceive('classifyProductsABC')
        ->once()
        ->andReturn(['classification' => 'class_a: [1], class_b: [], class_c: [2]']);
    app()->instance(InventoryAIService::class, $mock);

    actingAsUser('manager');
    $this->postJson('/api/v1/inventory/ai/classify-abc', [
        'products' => [
            ['product_id' => 1, 'annual_sales_value' => 50000, 'turnover_rate' => 12],
            ['product_id' => 2, 'annual_sales_value' => 500,   'turnover_rate' => 1],
        ],
    ])->assertOk()->assertJsonStructure(['classification']);
});

test('can detect obsolete products', function () {
    $mock = Mockery::mock(InventoryAIService::class);
    $mock->shouldReceive('detectObsoleteProducts')
        ->once()
        ->andReturn(['report' => 'product 1 is obsolete']);
    app()->instance(InventoryAIService::class, $mock);

    actingAsUser('manager');
    $this->postJson('/api/v1/inventory/ai/detect-obsolete', [
        'products' => [
            ['product_id' => 1, 'last_sold' => '2024-01-01', 'stock_qty' => 100],
            ['product_id' => 2, 'last_sold' => '2026-04-01', 'stock_qty' => 5],
        ],
    ])->assertOk()->assertJsonStructure(['report']);
});
