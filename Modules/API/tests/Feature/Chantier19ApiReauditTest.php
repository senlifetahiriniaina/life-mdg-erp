<?php

/**
 * Chantier 19 Lot 3 (API + Integration + Validation + Shared + Settings
 * re-verification, empirical-execution methodology): re-confirms — via
 * real HTTP requests against two real companies, not just re-reading the
 * code — that ApiKeyController/WebhookController/RequestLogController's
 * Chantier 10 company_id fix (see CLAUDE.md) actually isolates two real
 * tenants from each other, not just that the helper method looks correct.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\API\Models\ApiKey;

uses(RefreshDatabase::class);

function chantier19ApiUser(Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

test('api keys are isolated between two real companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier19ApiUser($companyA, 'admin');
    $userB = chantier19ApiUser($companyB, 'admin');

    $created = test()->actingAs($userA, 'sanctum')
        ->postJson('/api/v1/api/keys', ['name' => 'Company A key'])
        ->assertCreated();
    $keyId = $created->json('data.id');

    // Company B cannot see Company A's key in its own list.
    $listB = test()->actingAs($userB, 'sanctum')->getJson('/api/v1/api/keys')->assertOk();
    expect(collect($listB->json('data'))->pluck('id'))->not->toContain($keyId);

    // Company B cannot show or revoke Company A's key by guessing its id.
    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/api/keys/{$keyId}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/api/keys/{$keyId}/revoke")->assertOk();
    // revoke() blind-updates by id+tenant_id with no row match — confirm it
    // did NOT actually revoke Company A's key.
    expect(ApiKey::find($keyId)->revoked_at)->toBeNull();

    // Company A still sees and can revoke its own key.
    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/api/keys/{$keyId}")->assertOk();
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/api/keys/{$keyId}/revoke")->assertOk();
    expect(ApiKey::find($keyId)->revoked_at)->not->toBeNull();
});

// Chantier 32.5: "webhooks are isolated between two real companies" removed
// along with the whole ApiWebhook/WebhookController subtree it exercised —
// see the api_webhooks drop migration's own docblock for the full
// rationale (confirmed dead/insecure, superseded by the real, live
// App\Models\Webhook system at /api/v1/webhooks).

test('request logs are isolated between two real companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier19ApiUser($companyA, 'admin');
    $userB = chantier19ApiUser($companyB, 'admin');

    \Illuminate\Support\Facades\DB::table('api_requests')->insert([
        'tenant_id' => $companyA->id,
        'method' => 'GET', 'endpoint' => '/api/v1/probe', 'status_code' => 200,
        'duration_ms' => 10, 'created_at' => now(),
    ]);

    $listA = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/api/logs')->assertOk();
    expect($listA->json('data'))->toHaveCount(1);

    $listB = test()->actingAs($userB, 'sanctum')->getJson('/api/v1/api/logs')->assertOk();
    expect($listB->json('data'))->toHaveCount(0);
});

test('a role with zero api.* permissions still cannot bypass the module role gate', function () {
    $company = Company::factory()->create();
    $user = chantier19ApiUser($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/api/keys')->assertForbidden();
});
