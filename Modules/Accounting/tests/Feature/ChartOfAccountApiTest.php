<?php

declare(strict_types=1);

use Modules\Accounting\Models\ChartOfAccount;


// ── index ─────────────────────────────────────────────────────────────────────

test('can list chart of accounts', function () {
    actingAsUser();
    ChartOfAccount::factory()->count(3)->create();

    $this->getJson('/api/v1/accounting/chart-of-accounts')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('can filter accounts by type', function () {
    actingAsUser();
    ChartOfAccount::factory()->create(['type' => 'asset']);
    ChartOfAccount::factory()->create(['type' => 'expense']);

    $response = $this->getJson('/api/v1/accounting/chart-of-accounts?type=asset')
        ->assertOk();

    $data = $response->json('data');
    expect(collect($data)->every(fn ($a) => $a['type'] === 'asset'))->toBeTrue();
});

test('can search accounts by code', function () {
    actingAsUser();
    ChartOfAccount::factory()->create(['code' => '1001', 'name' => 'Cash']);
    ChartOfAccount::factory()->create(['code' => '2001', 'name' => 'Accounts Payable']);

    $response = $this->getJson('/api/v1/accounting/chart-of-accounts?search=1001')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.code'))->toBe('1001');
});

// ── store ─────────────────────────────────────────────────────────────────────

test('can create a chart of account', function () {
    actingAsUser();

    $this->postJson('/api/v1/accounting/chart-of-accounts', [
        'code' => '1100',
        'name' => 'Bank Account',
        'type' => 'asset',
    ])
        ->assertCreated()
        ->assertJsonFragment(['code' => '1100', 'name' => 'Bank Account', 'type' => 'asset']);

    $this->assertDatabaseHas('acc_chart_of_accounts', ['code' => '1100']);
});

test('can create a child account under a parent', function () {
    actingAsUser();
    $parent = ChartOfAccount::factory()->create(['code' => '1000', 'type' => 'asset']);

    $response = $this->postJson('/api/v1/accounting/chart-of-accounts', [
        'code' => '1001',
        'name' => 'Petty Cash',
        'type' => 'asset',
        'parent_id' => $parent->id,
    ])->assertCreated();

    // Resource may wrap under 'data' or return the object directly
    $parentId = $response->json('data.parent_id') ?? $response->json('parent_id');
    expect($parentId)->toBe($parent->id);
});

test('account code must be unique', function () {
    actingAsUser();
    ChartOfAccount::factory()->create(['code' => '9999']);

    $this->postJson('/api/v1/accounting/chart-of-accounts', [
        'code' => '9999',
        'name' => 'Duplicate',
        'type' => 'asset',
    ])->assertUnprocessable();
});

test('account type must be valid', function () {
    actingAsUser();

    $this->postJson('/api/v1/accounting/chart-of-accounts', [
        'code' => '9001',
        'name' => 'Bad Type',
        'type' => 'magic',
    ])->assertUnprocessable();
});

// ── show ─────────────────────────────────────────────────────────────────────

test('can retrieve a single account', function () {
    actingAsUser();
    $account = ChartOfAccount::factory()->create();

    $this->getJson("/api/v1/accounting/chart-of-accounts/{$account->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $account->id, 'code' => $account->code]);
});

test('show returns 404 for non-existent account', function () {
    actingAsUser();

    $this->getJson('/api/v1/accounting/chart-of-accounts/9999')->assertNotFound();
});

// ── update ────────────────────────────────────────────────────────────────────

test('can update an account name', function () {
    actingAsUser();
    $account = ChartOfAccount::factory()->create(['name' => 'Old Name']);

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$account->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'New Name']);

    expect($account->fresh()->name)->toBe('New Name');
});

test('can deactivate an account', function () {
    actingAsUser();
    $account = ChartOfAccount::factory()->create(['is_active' => true]);

    $this->putJson("/api/v1/accounting/chart-of-accounts/{$account->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonFragment(['is_active' => false]);
});

// ── destroy ───────────────────────────────────────────────────────────────────

test('can delete an account with no children', function () {
    actingAsUser();
    $account = ChartOfAccount::factory()->create();

    $this->deleteJson("/api/v1/accounting/chart-of-accounts/{$account->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('acc_chart_of_accounts', ['id' => $account->id]);
});

test('cannot delete an account that has children', function () {
    actingAsUser();
    $parent = ChartOfAccount::factory()->create();
    ChartOfAccount::factory()->create(['parent_id' => $parent->id]);

    $this->deleteJson("/api/v1/accounting/chart-of-accounts/{$parent->id}")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Cannot delete an account that has sub-accounts.']);
});

test('unauthenticated request returns 401', function () {
    $account = ChartOfAccount::factory()->create();

    $this->getJson("/api/v1/accounting/chart-of-accounts/{$account->id}")
        ->assertUnauthorized();
});
