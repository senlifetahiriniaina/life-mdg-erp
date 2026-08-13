<?php

declare(strict_types=1);

use Modules\BI\Services\AI\BiAIService;


test('can recommend dashboard configuration', function () {
    $mock = Mockery::mock(BiAIService::class);
    $mock->shouldReceive('recommendDashboard')
        ->once()
        ->andReturn(['recommendations' => 'dashboard recommendations', 'user_id' => 1]);
    app()->instance(BiAIService::class, $mock);

    actingAsUser('manager');
    $this->postJson('/api/v1/bi/ai/recommend-dashboard', [
        'user_id' => 1,
        'usage_history' => [['widget' => 'revenue_chart', 'views' => 45]],
        'available_kpis' => [['id' => 1, 'name' => 'Monthly Revenue']],
    ])->assertOk()->assertJsonStructure(['recommendations', 'user_id']);
});
