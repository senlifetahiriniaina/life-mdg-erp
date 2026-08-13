<?php

declare(strict_types=1);

use Modules\Accounting\Models\Journal;


// ── index ─────────────────────────────────────────────────────────────────────

test('can list journals', function () {
    actingAsUser();
    Journal::factory()->count(3)->create();

    $this->getJson('/api/v1/accounting/journals')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('can filter journals by type', function () {
    actingAsUser();
    Journal::factory()->create(['type' => 'sale']);
    Journal::factory()->create(['type' => 'bank']);

    $response = $this->getJson('/api/v1/accounting/journals?type=sale')
        ->assertOk();

    $data = $response->json('data');
    expect(collect($data)->every(fn ($j) => $j['type'] === 'sale'))->toBeTrue();
});

test('can search journals by name', function () {
    actingAsUser();
    Journal::factory()->create(['name' => 'Sales Revenue Journal']);
    Journal::factory()->create(['name' => 'Bank Account']);

    $response = $this->getJson('/api/v1/accounting/journals?search=Sales')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Sales Revenue Journal');
});

// ── store ─────────────────────────────────────────────────────────────────────

test('can create a journal', function () {
    actingAsUser();

    $this->postJson('/api/v1/accounting/journals', [
        'name' => 'General Ledger',
        'code' => 'GL001',
        'type' => 'general',
        'currency' => 'USD',
    ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'General Ledger', 'code' => 'GL001', 'type' => 'general']);

    $this->assertDatabaseHas('acc_journals', ['code' => 'GL001']);
});

test('journal code must be unique', function () {
    actingAsUser();
    Journal::factory()->create(['code' => 'DUP01']);

    $this->postJson('/api/v1/accounting/journals', [
        'name' => 'Another Journal',
        'code' => 'DUP01',
        'type' => 'general',
    ])->assertUnprocessable();
});

test('journal type must be valid', function () {
    actingAsUser();

    $this->postJson('/api/v1/accounting/journals', [
        'name' => 'Bad Journal',
        'code' => 'BAD01',
        'type' => 'invalid_type',
    ])->assertUnprocessable();
});

// ── show ─────────────────────────────────────────────────────────────────────

test('can retrieve a single journal', function () {
    actingAsUser();
    $journal = Journal::factory()->create();

    $this->getJson("/api/v1/accounting/journals/{$journal->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $journal->id, 'code' => $journal->code]);
});

test('show returns 404 for non-existent journal', function () {
    actingAsUser();

    $this->getJson('/api/v1/accounting/journals/9999')->assertNotFound();
});

// ── update ────────────────────────────────────────────────────────────────────

test('can update a journal', function () {
    actingAsUser();
    $journal = Journal::factory()->create(['name' => 'Old Name']);

    $this->putJson("/api/v1/accounting/journals/{$journal->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'New Name']);

    expect($journal->fresh()->name)->toBe('New Name');
});

test('can deactivate a journal', function () {
    actingAsUser();
    $journal = Journal::factory()->create(['is_active' => true]);

    $this->putJson("/api/v1/accounting/journals/{$journal->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonFragment(['is_active' => false]);
});

// ── destroy ───────────────────────────────────────────────────────────────────

test('can delete an empty journal', function () {
    actingAsUser();
    $journal = Journal::factory()->create();

    $this->deleteJson("/api/v1/accounting/journals/{$journal->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('acc_journals', ['id' => $journal->id]);
});

test('unauthenticated request returns 401', function () {
    $journal = Journal::factory()->create();

    $this->getJson("/api/v1/accounting/journals/{$journal->id}")
        ->assertUnauthorized();
});
