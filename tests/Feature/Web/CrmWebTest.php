<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CRM\Models\Contact;

uses(RefreshDatabase::class);

test('guest is redirected to login from contacts index', function () {
    $this->get('/crm/contacts')->assertRedirect('/login');
});

test('guest is redirected to login from contacts show', function () {
    $user = User::factory()->create();
    $contact = Contact::create([
        'first_name' => 'Jane',
        'last_name'  => 'Doe',
        'owner_id'   => $user->id,
    ]);

    $this->get("/crm/contacts/{$contact->id}")->assertRedirect('/login');
});

test('guest is redirected to login from contacts create', function () {
    $this->get('/crm/contacts/create')->assertRedirect('/login');
});

test('authenticated user sees contacts index', function () {
    $user = User::factory()->create();
    Contact::create([
        'first_name' => 'Jane',
        'last_name'  => 'Doe',
        'owner_id'   => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/crm/contacts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CRM/Contacts/Index')
            ->has('contacts')
            ->has('filters')
        );
});

test('contacts index returns paginated contacts', function () {
    $user = User::factory()->create();
    Contact::create(['first_name' => 'Alice', 'last_name' => 'Smith', 'owner_id' => $user->id]);
    Contact::create(['first_name' => 'Bob',   'last_name' => 'Jones', 'owner_id' => $user->id]);

    $this->actingAs($user)
        ->get('/crm/contacts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CRM/Contacts/Index')
            ->has('contacts.data', 2)
        );
});

test('search filter returns matching contacts', function () {
    $user = User::factory()->create();
    Contact::create(['first_name' => 'Jane',  'last_name' => 'Doe',  'owner_id' => $user->id]);
    Contact::create(['first_name' => 'Other', 'last_name' => 'User', 'owner_id' => $user->id]);

    $this->actingAs($user)
        ->get('/crm/contacts?search=Jane')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CRM/Contacts/Index')
            ->has('contacts.data', 1)
        );
});

test('show page loads with contact prop', function () {
    $user = User::factory()->create();
    $contact = Contact::create([
        'first_name' => 'Jane',
        'last_name'  => 'Doe',
        'owner_id'   => $user->id,
    ]);

    $this->actingAs($user)
        ->get("/crm/contacts/{$contact->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CRM/Contacts/Show')
            ->has('contact')
        );
});

test('show for non-existent contact returns 404', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/crm/contacts/99999')->assertNotFound();
});

test('authenticated user sees contacts create page', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/crm/contacts/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('CRM/Contacts/Create'));
});
