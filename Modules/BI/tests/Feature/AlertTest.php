<?php

declare(strict_types=1);

use Modules\BI\Models\BiAlert;


test('can list alerts', function () {
    actingAsUser('manager');

    $this
        ->getJson('/api/v1/bi/alerts')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('can create an alert', function () {
    $user = actingAsUser('manager');

    $response = $this
        ->postJson('/api/v1/bi/alerts', [
            'name' => 'Revenue Alert',
            'metric_name' => 'revenue',
            'condition_type' => 'above',
            'threshold' => 10000,
            'check_interval_minutes' => 60,
            'channels' => ['email'],
            'recipients' => [$user->id],
            'status' => 'active',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'Revenue Alert')
        ->assertJsonPath('condition_type', 'above');
});

test('can update an alert', function () {
    $user = actingAsUser('manager');

    $alert = BiAlert::create([
        'name' => 'Old Alert',
        'metric_name' => 'revenue',
        'condition_type' => 'above',
        'threshold' => 1000,
        'check_interval_minutes' => 60,
        'channels' => ['email'],
        'recipients' => [$user->id],
        'status' => 'active',
    ]);

    $this
        ->putJson("/api/v1/bi/alerts/{$alert->id}", ['name' => 'Updated Alert', 'threshold' => 5000])
        ->assertOk()
        ->assertJsonPath('name', 'Updated Alert');
});

test('can delete an alert', function () {
    $user = actingAsUser('manager');

    $alert = BiAlert::create([
        'name' => 'To Delete',
        'metric_name' => 'orders',
        'condition_type' => 'below',
        'threshold' => 100,
        'check_interval_minutes' => 30,
        'channels' => ['in_app'],
        'recipients' => [],
        'status' => 'active',
    ]);

    $this
        ->deleteJson("/api/v1/bi/alerts/{$alert->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('bi_alerts', ['id' => $alert->id]);
});

test('unauthenticated user cannot access alerts', function () {
    $this->getJson('/api/v1/bi/alerts')
        ->assertUnauthorized();
});
