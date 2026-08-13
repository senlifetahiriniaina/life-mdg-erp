<?php

declare(strict_types=1);

use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Widget;


test('can get drill-down data for a widget', function () {
    $user = actingAsUser('manager');

    $dashboard = Dashboard::create([
        'user_id' => $user->id,
        'name' => 'Drill Dashboard',
        'is_public' => false,
    ]);

    $widget = Widget::create([
        'dashboard_id' => $dashboard->id,
        'title' => 'Sales Chart',
        'type' => 'bar',
        'config' => ['dimensions' => ['year', 'month']],
        'refresh_interval' => 300,
    ]);

    $response = $this
        ->postJson("/api/v1/bi/widgets/{$widget->id}/drill", [
            'dimension' => 'year',
            'value' => '2026',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['widget', 'dimensions', 'result']);
});

test('drill-down returns available dimensions', function () {
    $user = actingAsUser('manager');

    $dashboard = Dashboard::create([
        'user_id' => $user->id,
        'name' => 'Dimensions Dashboard',
        'is_public' => false,
    ]);

    $widget = Widget::create([
        'dashboard_id' => $dashboard->id,
        'title' => 'Multi-dim Chart',
        'type' => 'bar',
        'config' => ['dimensions' => ['country', 'region', 'city']],
        'refresh_interval' => 300,
    ]);

    $response = $this
        ->postJson("/api/v1/bi/widgets/{$widget->id}/drill", []);

    $response->assertOk();
    $data = $response->json();
    expect($data['dimensions'])->toContain('country')
        ->and($data['dimensions'])->toContain('region');
});

test('unauthenticated user cannot access drill-down', function () {
    $this->postJson('/api/v1/bi/widgets/1/drill', [])
        ->assertUnauthorized();
});
