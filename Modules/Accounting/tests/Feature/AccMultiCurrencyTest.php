<?php

declare(strict_types=1);

use Modules\Accounting\Models\ExchangeRate;


beforeEach(function () {
    $this->user = actingAsUser('accountant');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can list exchange rates', function () {
    ExchangeRate::factory()->count(3)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/accounting/exchange-rates')
        ->assertOk()
        ->assertJsonPath('total', 3);
});

it('can create an exchange rate', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/exchange-rates', [
            'base_currency' => 'EUR',
            'target_currency' => 'USD',
            'rate' => 1.085000,
            'source' => 'manual',
            'date' => '2026-05-07',
        ])
        ->assertCreated()
        ->assertJsonPath('base_currency', 'EUR')
        ->assertJsonPath('target_currency', 'USD');
});

it('can fetch exchange rates (mock API)', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/exchange-rates/fetch')
        ->assertOk()
        ->assertJsonStructure(['fetched', 'rates']);
});

it('can view gain/loss summary', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/accounting/exchange-rates/gain-losses')
        ->assertOk()
        ->assertJsonStructure(['records', 'total_realized', 'total_unrealized']);
});
