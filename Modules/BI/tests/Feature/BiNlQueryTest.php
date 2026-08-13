<?php

declare(strict_types=1);



it('returns a bar chart for ventes question', function () {
    actingAsUser('manager');
    $this
        ->postJson('/api/v1/bi/nl-query', ['question' => 'Ventes par mois cette année'])
        ->assertOk()
        ->assertJsonPath('chart_type', 'bar')
        ->assertJsonStructure(['question', 'sql', 'data', 'chart_type']);
});

it('returns a donut chart for ticket question', function () {
    actingAsUser('manager');
    $this
        ->postJson('/api/v1/bi/nl-query', ['question' => 'Tickets par statut ce mois'])
        ->assertOk()
        ->assertJsonPath('chart_type', 'donut');
});

it('returns a kpi chart for unknown question', function () {
    actingAsUser('manager');
    $this
        ->postJson('/api/v1/bi/nl-query', ['question' => 'Combien d\'utilisateurs actifs?'])
        ->assertOk()
        ->assertJsonPath('chart_type', 'kpi');
});

it('validates question is required', function () {
    actingAsUser('manager');
    $this
        ->postJson('/api/v1/bi/nl-query', [])
        ->assertUnprocessable();
});
