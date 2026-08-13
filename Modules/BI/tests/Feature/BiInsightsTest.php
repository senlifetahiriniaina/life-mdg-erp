<?php

declare(strict_types=1);



it('returns a list of ai insights', function () {
    actingAsUser('manager');
    $this
        ->getJson('/api/v1/bi/insights')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'trend', 'title', 'text', 'module', 'value', 'severity']]]);
});

it('requires authentication to access insights', function () {
    $this->getJson('/api/v1/bi/insights')
        ->assertUnauthorized();
});
