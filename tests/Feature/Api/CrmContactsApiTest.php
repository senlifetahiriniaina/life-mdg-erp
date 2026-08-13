<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Contact;

uses(RefreshDatabase::class);

// ── Auth guard ────────────────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/crm/contacts')->assertUnauthorized();
    $this->postJson('/api/v1/crm/contacts', [])->assertUnauthorized();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated contacts', function () {
     $user = actingAsUser('employee');
    Contact::factory()->count(3)->create();
        $response = $this
        ->getJson('/api/v1/crm/contacts')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
});

test('index returns all contacts in data array', function () {
     $user = actingAsUser('employee');
    Contact::factory()->count(5)->create();
        $response = $this
        ->getJson('/api/v1/crm/contacts')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

test('index filters by status', function () {
     $user = actingAsUser('employee');
    Contact::factory()->count(2)->create(['status' => 'active']);
    Contact::factory()->create(['status' => 'inactive']);
        $response = $this
        ->getJson('/api/v1/crm/contacts?status=active')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('index searches by name', function () {
     $user = actingAsUser('employee');
    Contact::factory()->create(['first_name' => 'Unique', 'last_name' => 'Name']);
    Contact::factory()->create(['first_name' => 'Other', 'last_name' => 'Person']);
        $response = $this
        ->getJson('/api/v1/crm/contacts?search=Unique')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create a contact', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Alice',
            'last_name'  => 'Smith',
            'email'      => 'alice@example.com',
            'status'     => 'active',
        ])
        ->assertCreated()
        ->assertJsonFragment(['first_name' => 'Alice', 'last_name' => 'Smith']);

    expect(Contact::where('email', 'alice@example.com')->exists())->toBeTrue();
});

test('create requires first_name and last_name', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/crm/contacts', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name']);
});

test('create rejects invalid status', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Bob',
            'last_name'  => 'Jones',
            'status'     => 'invalid_status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('create rejects invalid email', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Bob',
            'last_name'  => 'Jones',
            'email'      => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('create assigns owner to authenticated user', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Carol',
            'last_name'  => 'White',
        ])
        ->assertCreated();

    expect($response->json('owner_id'))->toBe($user->id);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show a contact', function () {
     $user = actingAsUser('employee');
    $contact = Contact::factory()->create();
        $response = $this
        ->getJson("/api/v1/crm/contacts/{$contact->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $contact->id]);
});

test('show returns 404 for missing contact', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/crm/contacts/99999')
        ->assertNotFound();
});

// ── Update ────────────────────────────────────────────────────────────────────

test('can update a contact', function () {
     $user = actingAsUser('employee');
    $contact = Contact::factory()->create(['first_name' => 'Old']);
        $response = $this
        ->putJson("/api/v1/crm/contacts/{$contact->id}", ['first_name' => 'New'])
        ->assertOk()
        ->assertJsonFragment(['first_name' => 'New']);

    expect($contact->fresh()->first_name)->toBe('New');
});

test('update rejects invalid status value', function () {
     $user = actingAsUser('employee');
    $contact = Contact::factory()->create();
        $response = $this
        ->putJson("/api/v1/crm/contacts/{$contact->id}", ['status' => 'nope'])
        ->assertUnprocessable();
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('can delete a contact', function () {
     $user = actingAsUser('employee');
    $contact = Contact::factory()->create();
        $response = $this
        ->deleteJson("/api/v1/crm/contacts/{$contact->id}")
        ->assertNoContent();

    expect(Contact::find($contact->id))->toBeNull();
});
