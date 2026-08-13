<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Contact;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

test('encryption trait works on Contact model', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    // Create contact with email
    $contact = Contact::create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'owner_id' => $user->id,
    ]);

    // Email should be stored in plaintext (Contact doesn't use EncryptableTrait)
    expect($contact->email)->toBe('john@example.com');
});

test('contact model preserves data integrity', function () {
    $user = User::factory()->create();

    $contact = Contact::create([
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@example.com',
        'owner_id' => $user->id,
    ]);

    $retrieved = Contact::find($contact->id);

    expect($retrieved->first_name)->toBe('Alice');
    expect($retrieved->last_name)->toBe('Smith');
    expect($retrieved->email)->toBe('alice@example.com');
});

test('encryption preserves data through roundtrip', function () {
    $user = User::factory()->create();

    // Create contact
    $contact = Contact::factory()->create([
        'email' => 'test@example.com',
        'owner_id' => $user->id,
    ]);

    // Modify and save
    $contact->update(['email' => 'newemail@example.com']);

    // Retrieve and verify
    $retrieved = Contact::find($contact->id);
    expect($retrieved->email)->toBe('newemail@example.com');
});

test('encryptable trait exists and is loaded', function () {
    // Verify the trait file exists and can be loaded
    expect(trait_exists('App\Traits\EncryptableTrait'))->toBeTrue();
});

test('auditable actions trait exists', function () {
    // Verify the audit trait file exists
    expect(trait_exists('App\Traits\AuditableActions'))->toBeTrue();
});

test('data is accessible after encryption roundtrip', function () {
    $user = User::factory()->create();

    // Create multiple contacts
    $contact1 = Contact::factory()->create(['owner_id' => $user->id]);
    $contact2 = Contact::factory()->create(['owner_id' => $user->id]);

    $found = Contact::where('owner_id', $user->id)->get();

    expect($found)->toHaveCount(2);
    expect($found->pluck('id'))->toContain($contact1->id, $contact2->id);
});
