<?php

declare(strict_types=1);



test('authenticated user can list dashboards', function () {
    actingAsUser('manager');
    $this
        ->getJson('/api/v1/bi/dashboards')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total']]);
});

test('authenticated user can create a dashboard', function () {
    actingAsUser('manager');
    $this
        ->postJson('/api/v1/bi/dashboards', ['name' => 'Sales Overview'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Sales Overview');
});

test('authenticated user can create a KPI', function () {
    actingAsUser('manager');
    $this
        ->postJson('/api/v1/bi/kpis', [
            'name' => 'Monthly Revenue',
            'metric' => 'revenue',
            'source_module' => 'Accounting',
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Monthly Revenue');
});

test('unauthenticated user cannot access BI', function () {
    $this->getJson('/api/v1/bi/dashboards')
        ->assertUnauthorized();
});
