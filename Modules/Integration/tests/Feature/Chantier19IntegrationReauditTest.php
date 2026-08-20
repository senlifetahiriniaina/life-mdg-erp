<?php

declare(strict_types=1);

/**
 * Chantier 19 Lot 3 (Integration re-verification, empirical-execution
 * methodology): locks in 3 real bugs found and fixed by actually calling
 * the real HTTP routes/services against real seeded data, not just
 * re-reading the already-fixed IDOR/tenant-leak code documented in
 * CLAUDE.md's Chantier 8.5-light/10 entries for this module.
 *
 *  1. IntegrationController::store()/index() had zero authorize() call of
 *     any kind (unlike show/activate/addWebhook/dispatch/logs, fixed in
 *     Chantier 8.5-light) and the `connectors` route group carries no
 *     module:/role: gate either — any authenticated user of any role could
 *     create a connector for their own tenant with no permission check.
 *  2. WhbPartnerService::createInvite()'s `local` (same-server,
 *     cross-tenant) connection type validated the target tenant against
 *     `users.tenant_id` — the well-documented phantom column, never
 *     populated — so a real invite to a real, existing sibling tenant on
 *     the same server was always rejected with "Tenant not found".
 *  3. Fixing #2 surfaced a second, independent bug: `whb_connections.
 *     remote_server_url` is NOT NULL, but a `local` connection never
 *     supplies one — a guaranteed integrity-constraint violation on every
 *     local invite even once the tenant-lookup bug was fixed.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Models\IntegrationConnector;

uses(RefreshDatabase::class);

function chantier19IntegrationUser(Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

test('IntegrationController::store() rejects a user with zero integration.connector.create permission', function () {
    $company = Company::factory()->create();
    // sales-rep only ever gets crm.*/sales.* permissions in this app's
    // seeder — zero integration.* of any kind.
    $user = chantier19IntegrationUser($company, 'sales-rep');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/connectors', [
        'name'          => 'Unauthorized Connector',
        'provider_type' => 'webhook',
    ]);

    $response->assertForbidden();
    expect(IntegrationConnector::count())->toBe(0);
});

test('IntegrationController::store() succeeds for a real, permitted employee', function () {
    $company = Company::factory()->create();
    $user = chantier19IntegrationUser($company, 'employee');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/connectors', [
        'name'          => 'Authorized Connector',
        'provider_type' => 'webhook',
    ]);

    $response->assertCreated();
    expect(IntegrationConnector::where('tenant_id', (string) $company->id)->count())->toBe(1);
});

test('IntegrationController::index() rejects a user with zero integration.connector.view-any permission', function () {
    $company = Company::factory()->create();
    $user = chantier19IntegrationUser($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/integration/connectors')->assertForbidden();
});

test('WhbPartnerService: a real local (same-server) invite to an existing sibling tenant succeeds end-to-end', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier19IntegrationUser($companyA, 'admin');
    // Just needs to exist so createInvite()'s users.company_id lookup finds it.
    chantier19IntegrationUser($companyB, 'employee');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/whb/connections/invite', [
        'connection_type'  => 'local',
        'remote_tenant_id' => (string) $companyB->id,
    ]);

    $response->assertCreated();
    expect(\Modules\Integration\Models\WhbConnection::where('invite_code', $response->json('invite_code'))->first())
        ->not->toBeNull()
        ->local_tenant_id->toBe((string) $companyA->id)
        ->remote_tenant_id->toBe((string) $companyB->id)
        ->remote_server_url->toBeNull()
        ->status->toBe('pending');
});

test('WhbPartnerService: a local invite to a tenant that does not exist on this server is still rejected', function () {
    $companyA = Company::factory()->create();
    $userA = chantier19IntegrationUser($companyA, 'admin');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/whb/connections/invite', [
        'connection_type'  => 'local',
        'remote_tenant_id' => '999999',
    ]);

    $response->assertStatus(422);
});
