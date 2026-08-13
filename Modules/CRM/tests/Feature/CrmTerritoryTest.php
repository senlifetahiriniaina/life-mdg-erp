<?php

declare(strict_types=1);

use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Territory;
use Modules\CRM\Models\TerritoryAssignment;


it('can list territories with quota metrics', function () {
    $user = actingAsUser('admin');
    Territory::factory()->count(3)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/territories')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('data.0'))->toHaveKey('quota_attainment');
});

it('can auto-assign a contact to a territory by rules', function () {
    $user = actingAsUser('admin');

    $territory = Territory::factory()->create([
        'rules' => [['field' => 'status', 'value' => 'active']],
    ]);

    $contact = Contact::factory()->create(['status' => 'active']);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/territories/auto-assign', ['contact_id' => $contact->id])
        ->assertOk();

    expect($response->json('territory_id'))->toBe($territory->id);
    $this->assertDatabaseHas('crm_territory_assignments', [
        'territory_id' => $territory->id,
        'contact_id' => $contact->id,
        'auto_assigned' => 1,
    ]);
});

it('returns 422 when no territory matches contact rules', function () {
    $user = actingAsUser('admin');
    $contact = Contact::factory()->create(['status' => 'inactive']);

    Territory::factory()->create([
        'rules' => [['field' => 'status', 'value' => 'active']],
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/territories/auto-assign', ['contact_id' => $contact->id])
        ->assertStatus(422);
});

it('can get team quotas for all territories', function () {
    $user = actingAsUser('admin');
    Territory::factory()->count(2)->create(['is_active' => true]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/territories/team-quotas')
        ->assertOk();

    expect($response->json())->toBeArray();
    expect($response->json()[0])->toHaveKey('quota');
    expect($response->json()[0])->toHaveKey('attainment_pct');
});

it('can rebalance territory assignments', function () {
    $user = actingAsUser('admin');

    $territories = Territory::factory()->count(2)->create(['is_active' => true]);
    $contacts = Contact::factory()->count(4)->create();

    // Create assignments all in territory 1
    foreach ($contacts as $contact) {
        TerritoryAssignment::create([
            'territory_id' => $territories[0]->id,
            'contact_id' => $contact->id,
            'auto_assigned' => false,
        ]);
    }

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/territories/rebalance')
        ->assertOk();

    expect($response->json())->toHaveKey('rebalanced');
    expect($response->json())->toHaveKey('territories');
});
