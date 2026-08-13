<?php

declare(strict_types=1);

use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\OpenBankingConnection;
use Modules\Accounting\Services\OpenBankingService;


test('unauthenticated user cannot list open banking connections', function () {
    $this->getJson('/api/v1/accounting/open-banking/connections')
        ->assertUnauthorized();
});

test('authenticated user can list open banking connections', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/open-banking/connections')
        ->assertOk()
        ->assertJsonStructure([]);
});

test('authenticated user can initiate an open banking connection', function () {
    actingAsUser('accountant');
    $account = BankAccount::create([
        'name' => 'Main',
        'bank_name' => 'BNP Paribas',
        'currency' => 'EUR',
    ]);

    $response = $this->postJson('/api/v1/accounting/open-banking/connect', [
        'bank_account_id' => $account->id,
        'provider' => 'nordigen',
    ])
        ->assertOk()
        ->assertJsonStructure(['redirect_url']);

    expect($response->json('redirect_url'))->toContain('nordigen.com');
});

test('authenticated user can trigger sync on mocked service', function () {
    $user = actingAsUser('accountant');
    $account = BankAccount::create([
        'name' => 'Main',
        'bank_name' => 'BNP Paribas',
        'currency' => 'EUR',
    ]);

    $connection = OpenBankingConnection::create([
        'bank_account_id' => $account->id,
        'provider' => 'nordigen',
        'access_token' => 'token_test_abc123',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $mock = Mockery::mock(OpenBankingService::class);
    $mock->shouldReceive('syncTransactions')
        ->once()
        ->with(Mockery::on(fn ($c) => $c->id === $connection->id))
        ->andReturn(5);

    app()->instance(OpenBankingService::class, $mock);

    $this->postJson("/api/v1/accounting/open-banking/sync/{$connection->id}")
        ->assertOk()
        ->assertJsonPath('synced', 5);
});

test('sync returns 422 for non-active connection', function () {
    $user = actingAsUser('accountant');
    $account = BankAccount::create([
        'name' => 'Main',
        'bank_name' => 'BNP Paribas',
        'currency' => 'EUR',
    ]);

    $connection = OpenBankingConnection::create([
        'bank_account_id' => $account->id,
        'provider' => 'nordigen',
        'access_token' => 'token_expired',
        'status' => 'expired',
        'created_by' => $user->id,
    ]);

    $this->postJson("/api/v1/accounting/open-banking/sync/{$connection->id}")
        ->assertStatus(422);
});
