<?php

declare(strict_types=1);


beforeEach(function () {
    $this->user = actingAsUser('accountant');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('categorizes a known expense description correctly', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/smart-categorize', [
            'description' => 'Billet SNCF Paris-Lyon',
            'amount' => 89.00,
        ])
        ->assertOk()
        ->assertJsonPath('category', 'Transport')
        ->assertJsonPath('confidence', 0.85);
});

it('categorizes a restaurant expense correctly', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/smart-categorize', [
            'description' => 'Repas restaurant Le Bistrot',
            'amount' => 42.50,
        ])
        ->assertOk()
        ->assertJsonPath('category', 'Repas');
});

it('returns Autres charges for unknown description', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/smart-categorize', [
            'description' => 'Dépense inconnue xyz',
            'amount' => 15.00,
        ])
        ->assertOk()
        ->assertJsonPath('category', 'Autres charges');
});
