<?php

declare(strict_types=1);

use App\Models\User;
use Modules\CRM\Models\Contact;


test('authenticated user can list contacts', function () {
    $user = User::factory()->create();
    Contact::factory()->count(3)->create(['owner_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/contacts')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page']);
});

test('authenticated user can create a contact', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ])
        ->assertStatus(201)
        ->assertJsonPath('email', 'john@example.com');
});

test('contact creation requires first_name and last_name', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/contacts', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name']);
});

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/v1/crm/contacts')
        ->assertUnauthorized();
});

test('authenticated user can view a single contact', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/crm/contacts/{$contact->id}")
        ->assertOk()
        ->assertJsonPath('id', $contact->id)
        ->assertJsonPath('email', $contact->email);
});

test('authenticated user can update a contact', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/crm/contacts/{$contact->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ])
        ->assertOk()
        ->assertJsonPath('first_name', 'Updated');
});

test('authenticated user can delete a contact', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/crm/contacts/{$contact->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('crm_contacts', ['id' => $contact->id]);
});

test('contact list can be filtered by status', function () {
    $user = User::factory()->create();
    Contact::factory()->count(2)->create(['owner_id' => $user->id, 'status' => 'active']);
    Contact::factory()->count(3)->create(['owner_id' => $user->id, 'status' => 'inactive']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/contacts?status=active')
        ->assertOk();

    expect($response->json('total'))->toBe(2);
});

test('contact list can be searched by name', function () {
    $user = User::factory()->create();
    Contact::factory()->create([
        'owner_id' => $user->id,
        'first_name' => 'Alice',
        'last_name' => 'Wonder',
    ]);
    Contact::factory()->create([
        'owner_id' => $user->id,
        'first_name' => 'Bob',
        'last_name' => 'Builder',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/contacts?search=alice')
        ->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.first_name'))->toBe('Alice');
});

test('contact email must be valid when provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
