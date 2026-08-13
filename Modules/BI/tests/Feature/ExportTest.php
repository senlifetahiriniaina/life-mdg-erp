<?php

declare(strict_types=1);

use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Widget;


test('can export dashboard as PDF', function () {
    $user = actingAsUser('manager');

    $dashboard = Dashboard::create([
        'user_id' => $user->id,
        'name' => 'Test Dashboard',
        'is_public' => false,
    ]);

    $response = $this
        ->get("/api/v1/bi/dashboards/{$dashboard->id}/export?format=pdf");

    $response->assertOk();
    $contentType = $response->headers->get('Content-Type');
    expect($contentType)->toContain('pdf');
});

test('can export widget as CSV', function () {
    $user = actingAsUser('manager');

    $dashboard = Dashboard::create([
        'user_id' => $user->id,
        'name' => 'Test Dashboard',
        'is_public' => false,
    ]);

    $widget = Widget::create([
        'dashboard_id' => $dashboard->id,
        'title' => 'Test Widget',
        'type' => 'bar',
        'config' => null,
        'refresh_interval' => 300,
    ]);

    $response = $this
        ->get("/api/v1/bi/widgets/{$widget->id}/export?format=csv");

    $response->assertOk();
    $contentType = $response->headers->get('Content-Type');
    expect($contentType)->toContain('text/csv');
});

test('unauthenticated user cannot access BI exports', function () {
    $this->get('/api/v1/bi/dashboards/1/export?format=pdf')
        ->assertRedirect();
});
