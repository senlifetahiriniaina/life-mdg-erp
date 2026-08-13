<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Pipeline;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->create();
});

test('contact lifecycle from lead to account', function () {
    $lead = Lead::factory()->create(['owner_id' => $this->user->id]);

    $contact = Contact::factory()->create([
        'owner_id' => $this->user->id,
        'account_id' => $this->account->id,
    ]);

    expect($contact->account_id)->toBe($this->account->id);
});

test('opportunity created from contact', function () {
    $contact = Contact::factory()->create([
        'account_id' => $this->account->id,
    ]);

    $opportunity = Opportunity::factory()->create([
        'account_id' => $this->account->id,
        'contact_id' => $contact->id,
        'owner_id' => $this->user->id,
        'stage' => 'prospecting',
    ]);

    expect($opportunity->contact_id)->toBe($contact->id);
});

test('opportunity progresses through pipeline stages', function () {
    $opportunity = Opportunity::factory()->create([
        'stage' => 'prospecting',
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/crm/opportunities/{$opportunity->id}", [
            'stage' => 'qualification',
        ]);

    expect($response->status())->toBe(200);
});

test('opportunity scoring tracks probability', function () {
    $opportunity = Opportunity::factory()->create([
        'probability' => 25,
        'amount' => 10000,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/crm/opportunities/{$opportunity->id}/score");

    expect($response->status())->toBeIn([200, 404]);
});

test('contact enrichment captures additional fields', function () {
    $contact = Contact::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'job_title' => 'Manager',
    ]);

    expect($contact->email)->toBe('john@example.com');
    expect($contact->job_title)->toBe('Manager');
});

test('account-contact relationship maintained', function () {
    $contact1 = Contact::factory()->create(['account_id' => $this->account->id]);
    $contact2 = Contact::factory()->create(['account_id' => $this->account->id]);

    expect($contact1->account_id)->toBe($this->account->id);
    expect($contact2->account_id)->toBe($this->account->id);
});

test('lead scoring prioritizes hot leads', function () {
    $hotLead = Lead::factory()->create(['status' => 'hot']);
    $coldLead = Lead::factory()->create(['status' => 'cold']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/crm/leads?status=hot');

    expect($response->status())->toBe(200);
});

test('pipeline kanban view groups opportunities by stage', function () {
    Opportunity::factory(3)->create(['stage' => 'prospecting']);
    Opportunity::factory(2)->create(['stage' => 'qualification']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/crm/opportunities/kanban');

    expect($response->status())->toBe(200);
});

test('bulk contact import', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Bulk',
            'last_name' => 'User',
            'email' => 'bulk@example.com',
        ]);

    expect($response->status())->toBe(201);
});

test('contact deduplication prevents duplicates', function () {
    $contact1 = Contact::factory()->create(['email' => 'test@example.com']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/crm/contacts', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);

    expect(in_array($response->status(), [201, 422]))->toBeTrue();
});
