<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Account;

uses(RefreshDatabase::class);

// ── Auth & Authorization ──────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/crm/opportunities')->assertUnauthorized();
    $this->postJson('/api/v1/crm/opportunities', [])->assertUnauthorized();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated opportunities', function () {
    $user = actingAsUser('employee');
    Opportunity::factory()->count(20)->create();
    $response = $this
        ->getJson('/api/v1/crm/opportunities')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    expect($response->json('total'))->toBe(20);
});

test('index filters by stage', function () {
    $user = actingAsUser('employee');
    Opportunity::factory()->count(5)->create(['stage' => 'proposal']);
    Opportunity::factory()->count(3)->create(['stage' => 'negotiation']);
    $response = $this
        ->getJson('/api/v1/crm/opportunities?stage=proposal')
        ->assertOk();
    expect($response->json('total'))->toBe(5);
});

test('index filters by owner', function () {
    $user = actingAsUser('employee');
    $owner = $user;
    $other = actingAsUser('sales-rep');

    Opportunity::factory()->create(['owner_id' => $owner->id]);
    Opportunity::factory()->create(['owner_id' => $other->id]);

    $response = $this
        ->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/crm/opportunities?owner_id={$owner->id}")
        ->assertOk();
    expect($response->json('total'))->toBe(1);
});

test('index searches by name', function () {
    $user = actingAsUser('employee');
    Opportunity::factory()->create(['name' => 'Enterprise Deal']);
    Opportunity::factory()->create(['name' => 'Small Project']);
    $response = $this
        ->getJson('/api/v1/crm/opportunities?search=Enterprise')
        ->assertOk();
    expect($response->json('total'))->toBe(1);
});

test('index sorts by amount', function () {
    $user = actingAsUser('employee');
    Opportunity::factory()->create(['name' => 'Small', 'amount' => 10000]);
    Opportunity::factory()->create(['name' => 'Large', 'amount' => 500000]);
    $response = $this
        ->getJson('/api/v1/crm/opportunities?sort=-amount')
        ->assertOk();
    expect($response->json('data')[0]['name'])->toBe('Large');
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create opportunity with required fields', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create();
    $response = $this
        ->postJson('/api/v1/crm/opportunities', [
            'name'       => 'New Deal',
            'account_id' => $account->id,
            'amount'     => 50000,
        ])
        ->assertCreated();
    expect(Opportunity::where('name', 'New Deal')->exists())->toBeTrue();
});

test('can create opportunity with optional fields', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create();
    $response = $this
        ->postJson('/api/v1/crm/opportunities', [
            'name'                => 'Complex Deal',
            'account_id'          => $account->id,
            'amount'              => 250000,
            'stage'               => 'proposal',
            'probability'         => 75,
            'expected_close_date' => now()->addMonths(3)->toDateString(),
            'description'         => 'Strategic partnership opportunity',
        ])
        ->assertCreated()
        ->assertJsonFragment(['stage' => 'proposal']);
});

test('create requires name and amount', function () {
    $user = actingAsUser('employee');
    $this->postJson('/api/v1/crm/opportunities', ['account_id' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'amount']);
});

test('create validates amount is numeric', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create();
    $this->postJson('/api/v1/crm/opportunities', [
        'name'       => 'Deal',
        'account_id' => $account->id,
        'amount'     => 'not-a-number',
    ])->assertUnprocessable()->assertJsonValidationErrors(['amount']);
});

test('create validates probability range', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create();
    $this->postJson('/api/v1/crm/opportunities', [
        'name'        => 'Deal',
        'account_id'  => $account->id,
        'amount'      => 50000,
        'probability' => 150, // Invalid: > 100
    ])->assertUnprocessable()->assertJsonValidationErrors(['probability']);
});

test('created opportunity defaults to owner', function () {
    $user = actingAsUser('employee');
    $account = Account::factory()->create();
    $response = $this
        ->postJson('/api/v1/crm/opportunities', [
            'name'       => 'Deal',
            'account_id' => $account->id,
            'amount'     => 50000,
        ])
        ->assertCreated();
    $opp = Opportunity::find($response->json('id'));
    expect($opp->owner_id)->toBe($user->id);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show opportunity with relationships', function () {
    $user = actingAsUser('employee');
    $opp = Opportunity::factory()->create();
    $response = $this
        ->getJson("/api/v1/crm/opportunities/{$opp->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $opp->id]);
});

test('show returns 404 for missing opportunity', function () {
    $user = actingAsUser('employee');
    $this->getJson('/api/v1/crm/opportunities/99999')->assertNotFound();
});

// ── Update ────────────────────────────────────────────────────────────────────

test('can update opportunity stage', function () {
    $user = actingAsUser('employee');
    $opp = Opportunity::factory()->create(['stage' => 'qualification']);
    $this
        ->putJson("/api/v1/crm/opportunities/{$opp->id}", ['stage' => 'proposal'])
        ->assertOk();
    expect($opp->fresh()->stage)->toBe('proposal');
});

test('can update opportunity probability', function () {
    $user = actingAsUser('employee');
    $opp = Opportunity::factory()->create(['probability' => 50]);
    $this
        ->putJson("/api/v1/crm/opportunities/{$opp->id}", ['probability' => 75])
        ->assertOk();
    expect($opp->fresh()->probability)->toBe(75);
});

test('update validates probability range', function () {
    $user = actingAsUser('employee');
    $opp = Opportunity::factory()->create();
    $this->putJson("/api/v1/crm/opportunities/{$opp->id}", ['probability' => 200])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['probability']);
});

test('update can change amount', function () {
    $user = actingAsUser('employee');
    $opp = Opportunity::factory()->create(['amount' => 50000]);
    $this
        ->putJson("/api/v1/crm/opportunities/{$opp->id}", ['amount' => 75000])
        ->assertOk();
    expect((int) $opp->fresh()->amount)->toBe(75000);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('owner can delete opportunity', function () {
    $user = actingAsUser('employee');
    $opp = Opportunity::factory()->create(['owner_id' => $user->id]);
    $this->deleteJson("/api/v1/crm/opportunities/{$opp->id}")->assertNoContent();
    expect(Opportunity::find($opp->id))->toBeNull();
});

test('delete returns 404 for missing opportunity', function () {
    $user = actingAsUser('employee');
    $this->deleteJson('/api/v1/crm/opportunities/99999')->assertNotFound();
});

// ── Filtering & Aggregation ───────────────────────────────────────────────────

test('can filter by amount range', function () {
    $user = actingAsUser('employee');
    Opportunity::factory()->create(['name' => 'Small', 'amount' => 10000]);
    Opportunity::factory()->create(['name' => 'Medium', 'amount' => 50000]);
    Opportunity::factory()->create(['name' => 'Large', 'amount' => 500000]);

    $response = $this
        ->getJson('/api/v1/crm/opportunities?amount_min=25000&amount_max=100000')
        ->assertOk();
    expect($response->json('total'))->toBe(1);
});

test('can filter by expected close date range', function () {
    $user = actingAsUser('employee');
    Opportunity::factory()->create([
        'name'                => 'Soon',
        'expected_close_date' => now()->addDays(5)->toDateString(),
    ]);
    Opportunity::factory()->create([
        'name'                => 'Later',
        'expected_close_date' => now()->addMonths(6)->toDateString(),
    ]);

    $response = $this
        ->getJson('/api/v1/crm/opportunities?close_date_before=' . now()->addMonths(3)->toDateString())
        ->assertOk();
    expect($response->json('total'))->toBe(1);
});

test('can get pipeline summary by stage', function () {
    $user = actingAsUser('employee');
    Opportunity::factory()->create(['stage' => 'qualification', 'amount' => 50000]);
    Opportunity::factory()->count(2)->create(['stage' => 'proposal', 'amount' => 100000]);
    Opportunity::factory()->create(['stage' => 'negotiation', 'amount' => 250000]);

    $response = $this
        ->getJson('/api/v1/crm/opportunities/pipeline')
        ->assertOk();
    // Pipeline endpoint should aggregate by stage
    expect($response->json())->toHaveKey('qualification');
});
