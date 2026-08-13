<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Contact;

uses(RefreshDatabase::class);

// ── Auth & Authorization ──────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/crm/accounts')->assertUnauthorized();
    $this->postJson('/api/v1/crm/accounts', [])->assertUnauthorized();
});

test('employee cannot delete account', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create();
    $this->deleteJson("/api/v1/crm/accounts/{$account->id}")->assertForbidden();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated accounts', function () {
    $user = actingAsUser('employee');
    Account::factory()->count(15)->create();
    $response = $this
        ->getJson('/api/v1/crm/accounts')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    expect($response->json('total'))->toBe(15);
});

test('index filters by type', function () {
    $user = actingAsUser('employee');
    Account::factory()->count(3)->create(['type' => 'customer']);
    Account::factory()->count(2)->create(['type' => 'prospect']);
    $response = $this
        ->getJson('/api/v1/crm/accounts?type=customer')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('index filters by industry', function () {
    $user = actingAsUser('employee');
    Account::factory()->create(['industry' => 'Technology']);
    Account::factory()->create(['industry' => 'Healthcare']);
    $response = $this
        ->getJson('/api/v1/crm/accounts?industry=Technology')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('index searches by name and email', function () {
    $user = actingAsUser('employee');
    Account::factory()->create(['name' => 'Acme Corp', 'email' => 'contact@acme.com']);
    Account::factory()->create(['name' => 'TechStart Inc']);
    $response = $this->getJson('/api/v1/crm/accounts?search=Acme')->assertOk()->assertJsonCount(1, 'data');
    $response = $this->getJson('/api/v1/crm/accounts?search=contact@acme.com')->assertOk()->assertJsonCount(1, 'data');
});

test('index sorts by revenue', function () {
    $user = actingAsUser('employee');
    Account::factory()->create(['name' => 'Small', 'annual_revenue' => 100000]);
    Account::factory()->create(['name' => 'Large', 'annual_revenue' => 5000000]);
    $response = $this
        ->getJson('/api/v1/crm/accounts?sort=-annual_revenue')
        ->assertOk();
    $accounts = $response->json('data');
    expect($accounts[0]['name'])->toBe('Large');
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create account with required fields', function () {
    $user = actingAsUser('employee');
    $response = $this
        ->postJson('/api/v1/crm/accounts', [
            'name' => 'New Corp',
            'type' => 'customer',
        ])
        ->assertCreated();
    expect(Account::where('name', 'New Corp')->exists())->toBeTrue();
});

test('can create account with all optional fields', function () {
    $user = actingAsUser('employee');
    $response = $this
        ->postJson('/api/v1/crm/accounts', [
            'name'     => 'Full Account',
            'type'     => 'prospect',
            'industry' => 'Technology',
            'email'    => 'info@fullaccount.com',
            'phone'    => '+1-555-1234',
            'website'  => 'https://fullaccount.com',
            'revenue'  => 2500000,
            'status'   => 'active',
        ])
        ->assertCreated()
        ->assertJsonFragment(['industry' => 'Technology']);
});

test('create requires name', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/crm/accounts', ['type' => 'customer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('create validates email format', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/crm/accounts', [
        'name'  => 'Test',
        'type'  => 'customer',
        'email' => 'not-an-email',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

test('create rejects invalid type', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/crm/accounts', [
        'name' => 'Test',
        'type' => 'invalid',
    ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
});

test('created account belongs to user tenant', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/crm/accounts', [
        'name' => 'Test Account',
        'type' => 'customer',
    ])->assertCreated();
    $account = Account::where('name', 'Test Account')->first();
    expect($account->tenant_id)->toBe($user->tenant_id);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show account with relationships', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->has(Contact::factory()->count(2))->create();
    $response = $this
        ->getJson("/api/v1/crm/accounts/{$account->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $account->id]);
});

test('show returns 404 for missing account', function () {
    $user = actingAsUser('employee');
    $this->getJson('/api/v1/crm/accounts/99999')->assertNotFound();
});

test('show returns 403 when accessing other tenant account', function () {
    $user1 = actingAsUser('employee');
    $account = Account::factory()->create();

    $user2 = User::factory()->create(['tenant_id' => 999]);
    $this->actingAs($user2, 'sanctum')
        ->getJson("/api/v1/crm/accounts/{$account->id}")
        ->assertForbidden();
});

// ── Update ────────────────────────────────────────────────────────────────────

test('can update account fields', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create(['name' => 'Old Name']);
    $this
        ->putJson("/api/v1/crm/accounts/{$account->id}", ['name' => 'New Name'])
        ->assertOk();
    expect($account->fresh()->name)->toBe('New Name');
});

test('update validates email format', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create();
    $this->putJson("/api/v1/crm/accounts/{$account->id}", ['email' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('update preserves unchanged fields', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create([
        'name'  => 'Company A',
        'email' => 'info@company.com',
    ]);
    $this->putJson("/api/v1/crm/accounts/{$account->id}", ['name' => 'Company B'])->assertOk();
    $fresh = $account->fresh();
    expect($fresh->name)->toBe('Company B');
    expect($fresh->email)->toBe('info@company.com');
});

test('update returns 404 for missing account', function () {
    $user = actingAsUser('employee');
    $this->putJson('/api/v1/crm/accounts/99999', ['name' => 'Test'])->assertNotFound();
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('manager can delete account', function () {
    $user = actingAsUser('manager');
    $account = Account::factory()->create();
    $this->deleteJson("/api/v1/crm/accounts/{$account->id}")->assertNoContent();
    expect(Account::find($account->id))->toBeNull();
});

test('delete cascades to contacts', function () {
    $user = actingAsUser('manager');
    $account = Account::factory()->has(Contact::factory()->count(3))->create();
    $this->deleteJson("/api/v1/crm/accounts/{$account->id}")->assertNoContent();
    expect(Contact::where('account_id', $account->id)->count())->toBe(0);
});

test('delete returns 404 for missing account', function () {
    $user = actingAsUser('manager');
    $this->deleteJson('/api/v1/crm/accounts/99999')->assertNotFound();
});

// ── Bulk Operations ───────────────────────────────────────────────────────────

test('can filter by multiple statuses', function () {
    $user = actingAsUser('employee');
    Account::factory()->create(['status' => 'active']);
    Account::factory()->create(['status' => 'inactive']);
    Account::factory()->create(['status' => 'prospect']);
    $response = $this
        ->getJson('/api/v1/crm/accounts?status=active,prospect')
        ->assertOk();
    expect($response->json('total'))->toBe(2);
});

test('pagination works correctly', function () {
    $user = actingAsUser('employee');
    Account::factory()->count(25)->create();
    $page1 = $this->getJson('/api/v1/crm/accounts?per_page=10&page=1')->assertOk();
    expect($page1->json('current_page'))->toBe(1);
    expect($page1->json('total'))->toBe(25);

    $page2 = $this->getJson('/api/v1/crm/accounts?per_page=10&page=2')->assertOk();
    expect($page2->json('current_page'))->toBe(2);
});
