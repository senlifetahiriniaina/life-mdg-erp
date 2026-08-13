<?php

declare(strict_types=1);

use Modules\Accounting\Models\AccBankFeed;
use Modules\Accounting\Models\AccBankFeedTransaction;
use Modules\Accounting\Models\AccOpenBankingConnection;


it('can list open banking connections', function () {
    actingAsUser('accountant');
    AccOpenBankingConnection::factory()->count(3)->create();

    $this->getJson('/api/v1/accounting/open-banking/connections')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can connect a bank', function () {
    actingAsUser('accountant');

    $response = $this->postJson('/api/v1/accounting/open-banking/connections', [
        'bank_code' => 'bnp',
        'auth_code' => 'mock_auth_code_123',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.bank_code', 'bnp')
        ->assertJsonPath('data.status', 'active');

    expect(AccOpenBankingConnection::where('bank_code', 'bnp')->exists())->toBeTrue();
    expect(AccBankFeed::count())->toBe(1);
});

it('can sync transactions from a connection', function () {
    actingAsUser('accountant');
    /** @var AccOpenBankingConnection $connection */
    $connection = AccOpenBankingConnection::factory()->active()->create();
    AccBankFeed::factory()->create(['connection_id' => $connection->id]);

    $response = $this->postJson("/api/v1/accounting/open-banking/connections/{$connection->id}/sync")
        ->assertOk()
        ->assertJsonStructure(['synced', 'synced_at']);

    expect($response->json('synced'))->toBeGreaterThan(0);
    expect(AccBankFeedTransaction::count())->toBeGreaterThan(0);
});

it('transactions are categorized automatically after sync', function () {
    actingAsUser('accountant');
    /** @var AccOpenBankingConnection $connection */
    $connection = AccOpenBankingConnection::factory()->active()->create();
    AccBankFeed::factory()->create(['connection_id' => $connection->id]);

    $this->postJson("/api/v1/accounting/open-banking/connections/{$connection->id}/sync")
        ->assertOk();

    $transactions = AccBankFeedTransaction::all();
    foreach ($transactions as $tx) {
        expect($tx->category)->not->toBeNull('Transaction should have a category');
    }
});

it('can match transaction to journal entry', function () {
    actingAsUser('accountant');
    /** @var AccOpenBankingConnection $connection */
    $connection = AccOpenBankingConnection::factory()->active()->create();
    $feed = AccBankFeed::factory()->create(['connection_id' => $connection->id]);
    $tx = AccBankFeedTransaction::factory()->statusNew()->create(['feed_id' => $feed->id]);

    $this->patchJson("/api/v1/accounting/open-banking/transactions/{$tx->id}", [
        'journal_entry_id' => 42,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'matched')
        ->assertJsonPath('data.journal_entry_id', 42);
});

it('can ignore a transaction', function () {
    actingAsUser('accountant');
    /** @var AccOpenBankingConnection $connection */
    $connection = AccOpenBankingConnection::factory()->active()->create();
    $feed = AccBankFeed::factory()->create(['connection_id' => $connection->id]);
    $tx = AccBankFeedTransaction::factory()->statusNew()->create(['feed_id' => $feed->id]);

    $this->patchJson("/api/v1/accounting/open-banking/transactions/{$tx->id}", [
        'status' => 'ignored',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ignored');

    expect($tx->fresh()->status)->toBe('ignored');
});

it('returns 422 when syncing an inactive connection', function () {
    actingAsUser('accountant');
    /** @var AccOpenBankingConnection $connection */
    $connection = AccOpenBankingConnection::factory()->inactive()->create();

    $this->postJson("/api/v1/accounting/open-banking/connections/{$connection->id}/sync")
        ->assertStatus(422);
});

it('can list transactions filtered by status', function () {
    actingAsUser('accountant');
    /** @var AccOpenBankingConnection $connection */
    $connection = AccOpenBankingConnection::factory()->active()->create();
    $feed = AccBankFeed::factory()->create(['connection_id' => $connection->id]);
    AccBankFeedTransaction::factory()->statusNew()->count(3)->create(['feed_id' => $feed->id]);
    AccBankFeedTransaction::factory()->statusIgnored()->count(2)->create(['feed_id' => $feed->id]);

    $this->getJson('/api/v1/accounting/open-banking/transactions?status=new')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
