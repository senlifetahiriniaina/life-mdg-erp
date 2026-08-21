<?php

declare(strict_types=1);

/**
 * Chantier 32.6 — 14-layer deep audit of Modules\Integration.
 *
 * Locks in every real bug found by actually executing the code (tinker/real
 * HTTP requests), not by re-reading the already-documented Chantier
 * 8.5-light/19 fixes:
 *
 *  1. integrations.integration_key had a GLOBAL unique index — a second
 *     tenant connecting to the same integration key fataled with a real
 *     UniqueConstraintViolationException (confirmed empirically via
 *     tinker before this chantier's migration fix).
 *  2. Modules\Integration\Services\IntegrationManager (the mobile-money/
 *     e-commerce/business-tools registry — Orange Money, Wave, MTN MoMo,
 *     M-Pesa, Shopify, WooCommerce, Jumia, Google Workspace, Zapier) had
 *     ZERO controller/route/policy/permission anywhere — activated for
 *     real via ExternalIntegrationController.
 *  3. testConnection()/sync() previously either threw an uncaught
 *     undefined-method Error (5 of 9 connectors have no ping()) or
 *     silently "succeeded" with 0 records synced (none of the 9
 *     connectors implement syncIn()/syncOut()) — both now fail loudly and
 *     honestly with a real RuntimeException instead.
 *  4. WhbDataSerializerService::serialize() never actually fetched the
 *     real invoice/purchase_order/quote/contact/inventory record — always
 *     transmitted hardcoded empty placeholder data over WHB regardless of
 *     which resourceId was referenced. Fixed for the 5 data types with an
 *     unambiguous real single-model backing, with a real tenant-ownership
 *     IDOR guard on the 2 that have a genuinely populated scoping column.
 *  5. WhbFederationController::wellKnown() had zero route anywhere — this
 *     server has never been discoverable by a federation partner.
 *  6. IntegrationController had no destroy()/DELETE route despite
 *     IntegrationConnectorPolicy::delete() existing since Chantier 8.6 and
 *     IntegrationsIndex.vue's disconnectConnector() always calling it.
 *  7. WebhookEndpoint.secret_key was never hidden — plaintext HMAC secret
 *     exposed via IntegrationController::show()/logs()'s JSON response.
 *  8. The main `connectors`/stats/supabase/firebase route group and the
 *     ai/assist group had no module:Integration gate — BackendStatus
 *     Controller's Supabase/Firebase test endpoints had zero authorize()
 *     of any kind.
 *  9. IntegrationService::dispatchWebhook() issued sequential blocking
 *     HTTP calls per endpoint inside the request-response cycle —
 *     rewritten onto Http::pool() for real concurrency, same synchronous
 *     return-with-results contract.
 * 10. WhbPartnerService::approveConnection()/rejectConnection()/
 *     suspendConnection() had zero tenant filter of their own (safe only
 *     because the one real caller pre-checks) — hardened with an optional
 *     tenant-scoped lookup as a defense-in-depth landmine closure.
 *
 * Also re-confirms (not just re-reads) the pre-existing Chantier 8.5-light/
 * 19 IDOR fixes on IntegrationConnectorPolicy and WhbPartnerController's
 * approve/reject/suspend are still genuinely correct after everything
 * since, and that the real federation HMAC signature protection
 * (VerifyFederationSignature) is still intact.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;
use Modules\CRM\Models\Contact;
use Modules\Integration\Models\Integration;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Models\WebhookEndpoint;
use Modules\Integration\Models\WhbConnection;
use Modules\Integration\Services\WhbDataSerializerService;
use Modules\Integration\Services\WhbFederationService;
use Modules\Integration\Services\WhbPartnerService;

uses(RefreshDatabase::class);

function chantier326User(Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ---------------------------------------------------------------------------
// 1. Tenant-scoped unique key (migration fix)
// ---------------------------------------------------------------------------

test('two different tenants can both connect the same integration key without a unique-constraint fatal', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier326User($companyA, 'admin');
    $userB = chantier326User($companyB, 'admin');

    $resA = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/integration/external/orange-money/connect', [
        'credentials' => ['api_key' => 'key-a'],
    ]);
    $resB = test()->actingAs($userB, 'sanctum')->postJson('/api/v1/integration/external/orange-money/connect', [
        'credentials' => ['api_key' => 'key-b'],
    ]);

    $resA->assertCreated();
    $resB->assertCreated();
    expect(Integration::where('integration_key', 'orange-money')->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// 2-3. ExternalIntegrationController — activation of IntegrationManager
// ---------------------------------------------------------------------------

test('external integrations index lists the real registry with per-tenant status', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'employee');

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/integration/external');

    $response->assertOk();
    $keys = collect($response->json('data'))->pluck('key');
    expect($keys)->toContain('orange-money', 'wave', 'mtn-momo', 'mpesa', 'shopify', 'woocommerce', 'jumia', 'google-workspace', 'zapier');
    expect(collect($response->json('data'))->firstWhere('key', 'wave')['status'])->toBe('disconnected');
});

test('external integrations connect/disconnect round trip works end-to-end', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'employee');

    $connect = test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/external/wave/connect', [
        'credentials' => ['api_key' => 'x', 'secret_key' => 'y', 'webhook_secret' => 'z'],
    ]);
    $connect->assertCreated();
    expect(Integration::where('tenant_id', (string) $company->id)->where('integration_key', 'wave')->first()->status)->toBe('connected');

    $disconnect = test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/external/wave/disconnect');
    $disconnect->assertOk();
    expect(Integration::where('tenant_id', (string) $company->id)->where('integration_key', 'wave')->first()->status)->toBe('disconnected');
});

test('external integrations reject an unknown registry key with 404, not a 500', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'employee');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/external/not-a-real-key/connect', [
        'credentials' => ['x' => 'y'],
    ]);

    $response->assertStatus(404);
});

test('external integrations deny a role with zero integration.* permissions', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/integration/external')->assertForbidden();
    test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/external/wave/connect', [
        'credentials' => ['api_key' => 'x'],
    ])->assertForbidden();
});

test('external integrations test/sync a connector another tenant owns is a 404, never leaks its status', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier326User($companyA, 'admin');
    $userB = chantier326User($companyB, 'admin');

    test()->actingAs($userA, 'sanctum')->postJson('/api/v1/integration/external/wave/connect', [
        'credentials' => ['api_key' => 'x'],
    ])->assertCreated();

    // Company B never connected 'wave' at all — their own lookup 404s
    // before it could ever cross into A's row.
    test()->actingAs($userB, 'sanctum')->postJson('/api/v1/integration/external/wave/test')->assertStatus(404);
    test()->actingAs($userB, 'sanctum')->postJson('/api/v1/integration/external/wave/disconnect')->assertStatus(404);
});

test('IntegrationManager::testConnection() fails loudly and honestly for a connector with no real ping()', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'admin');

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/external/shopify/connect', [
        'credentials' => ['shop_domain' => 'x.myshopify.com', 'access_token' => 'tok'],
    ])->assertCreated();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/external/shopify/test');

    // Previously: an uncaught undefined-method Error (ShopifyConnector has
    // no ping()) — now a clean, honest 501, never a fatal 500.
    $response->assertStatus(501);
    expect($response->json('error'))->toContain('shopify');
});

test('IntegrationManager::sync() fails loudly and honestly rather than a misleading fake success', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'admin');

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/external/woocommerce/connect', [
        'credentials' => ['site_url' => 'https://x.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs'],
    ])->assertCreated();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/external/woocommerce/sync');

    $response->assertStatus(501);
    // Confirm no misleading "success" IntegrationSyncLog was ever written.
    expect(\Modules\Integration\Models\IntegrationSyncLog::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// 4. WhbDataSerializerService — real data, not empty stubs
// ---------------------------------------------------------------------------

test('WhbDataSerializerService::serialize() transmits the real invoice total and lines, not an empty stub', function () {
    $invoice = Invoice::factory()->create(['total' => 45000, 'number' => 'INV-000777']);
    InvoiceLine::factory()->create([
        'invoice_id'  => $invoice->id,
        'description' => 'Chemises EPI',
        'quantity'    => 3,
        'unit_price'  => 15000,
        'total'       => 45000,
    ]);

    $payload = app(WhbDataSerializerService::class)->serialize('invoice', $invoice->id, '1');

    expect($payload['total'])->toBe(45000.0)
        ->and($payload['number'])->toBe('INV-000777')
        ->and($payload['lines'])->toHaveCount(1)
        ->and($payload['lines'][0]['description'])->toBe('Chemises EPI');
});

test('WhbDataSerializerService::serialize() throws for a resourceId that does not exist, rather than a fake empty payload', function () {
    expect(fn () => app(WhbDataSerializerService::class)->serialize('invoice', 999999, '1'))
        ->toThrow(RuntimeException::class);
});

test('WhbDataSerializerService::serialize() refuses to send another tenant\'s purchase order', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $po = PurchaseOrder::factory()->create(['company_id' => $companyA->id, 'total' => 10000]);
    PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id]);

    $ownPayload = app(WhbDataSerializerService::class)->serialize('purchase_order', $po->id, (string) $companyA->id);
    expect($ownPayload['total'])->toBe(10000.0);

    expect(fn () => app(WhbDataSerializerService::class)->serialize('purchase_order', $po->id, (string) $companyB->id))
        ->toThrow(RuntimeException::class);
});

test('WhbPartnerController::send() end-to-end (local connection) transmits the real invoice via WHB', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier326User($companyA, 'admin');
    chantier326User($companyB, 'employee');

    $invoice = Invoice::factory()->create(['total' => 99000, 'number' => 'INV-WHB-1']);

    $connection = WhbConnection::create([
        'local_tenant_id'  => (string) $companyA->id,
        'remote_tenant_id' => (string) $companyB->id,
        'connection_type'  => 'local',
        'status'           => 'active',
    ]);
    \Modules\Integration\Models\WhbPermission::create([
        'connection_id' => $connection->id,
        'data_type'     => 'invoice',
        'can_send'      => true,
        'can_receive'   => false,
        'auto_accept'   => true,
    ]);

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/whb/send', [
        'connection_id' => $connection->id,
        'data_type'     => 'invoice',
        'resource_id'   => $invoice->id,
    ]);

    $response->assertOk();
    $exchange = \Modules\Integration\Models\WhbExchange::where('connection_id', $connection->id)->first();
    // A whole-number float round-trips through the array cast's JSON encode/
    // decode as a plain int (json_encode(99000.0) === "99000" without
    // JSON_PRESERVE_ZERO_FRACTION) — a PHP/JSON quirk, not an app bug, so
    // compare loosely rather than assert an exact float type.
    expect((float) $exchange->payload['total'])->toBe(99000.0)
        ->and($exchange->payload['number'])->toBe('INV-WHB-1');
});

// ---------------------------------------------------------------------------
// 5. .well-known/widehalo federation discovery route
// ---------------------------------------------------------------------------

test('.well-known/widehalo is publicly reachable without authentication', function () {
    $response = test()->getJson('/.well-known/widehalo');

    $response->assertOk();
    expect($response->json('api'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// 6-7. destroy() route + secret_key hidden
// ---------------------------------------------------------------------------

test('a connector can be deleted via the real DELETE route IntegrationsIndex.vue has always called', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'admin');

    $connector = IntegrationConnector::create([
        'tenant_id' => (string) $company->id, 'name' => 'To Delete', 'slug' => 'to-delete',
        'provider_type' => 'webhook', 'status' => 'inactive', 'created_by' => $user->id,
    ]);

    $response = test()->actingAs($user, 'sanctum')->deleteJson("/api/v1/integration/connectors/{$connector->id}");

    $response->assertOk();
    expect(IntegrationConnector::find($connector->id))->toBeNull();
    expect(IntegrationConnector::withTrashed()->find($connector->id))->not->toBeNull();
});

test('another tenant cannot delete a connector they do not own', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userB = chantier326User($companyB, 'admin');

    $connector = IntegrationConnector::create([
        'tenant_id' => (string) $companyA->id, 'name' => 'Not Yours', 'slug' => 'not-yours',
        'provider_type' => 'webhook', 'status' => 'inactive', 'created_by' => 1,
    ]);

    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/integration/connectors/{$connector->id}")->assertForbidden();
    expect(IntegrationConnector::find($connector->id))->not->toBeNull();
});

test('webhook secret_key is never exposed in the connector detail JSON response', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'admin');

    $connector = IntegrationConnector::create([
        'tenant_id' => (string) $company->id, 'name' => 'Signed', 'slug' => 'signed',
        'provider_type' => 'webhook', 'status' => 'active', 'created_by' => $user->id,
    ]);
    WebhookEndpoint::create([
        'connector_id' => $connector->id, 'url' => 'https://example.test/hook', 'method' => 'POST',
        'secret_key' => 'super-secret-value', 'is_active' => true, 'retry_attempts' => 3, 'timeout_seconds' => 30,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson("/api/v1/integration/connectors/{$connector->id}");

    $response->assertOk();
    expect($response->getContent())->not->toContain('super-secret-value');
    expect($response->json('webhook_endpoints.0'))->not->toHaveKey('secret_key');
});

// ---------------------------------------------------------------------------
// 8. module:Integration gate on BackendStatusController (Firebase/Supabase)
// ---------------------------------------------------------------------------

test('a role with zero integration.* permissions cannot reach Supabase/Firebase test endpoints', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'sales-rep');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/integration/supabase/status')->assertForbidden();
    test()->actingAs($user, 'sanctum')->postJson('/api/v1/integration/firebase/test-push', [
        'device_token' => 'abc',
    ])->assertForbidden();
});

test('a permitted employee can reach the Firebase/Supabase status endpoints (module gate does not over-block)', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'employee');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/integration/firebase/status')->assertOk();
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/integration/supabase/status')->assertOk();
});

// ---------------------------------------------------------------------------
// 9. dispatchWebhook() concurrency (Http::pool()) — Layer 14f perf fix
// ---------------------------------------------------------------------------

test('dispatchWebhook fires multiple active endpoints concurrently and aggregates real per-endpoint results', function () {
    Http::fake([
        'https://ok.example.test/*'   => Http::response(['ok' => true], 200),
        'https://fail.example.test/*' => Http::response('Server Error', 500),
    ]);

    $company = Company::factory()->create();
    $user = chantier326User($company, 'admin');

    $connector = IntegrationConnector::create([
        'tenant_id' => (string) $company->id, 'name' => 'Multi', 'slug' => 'multi',
        'provider_type' => 'webhook', 'status' => 'active', 'created_by' => $user->id,
    ]);
    WebhookEndpoint::create(['connector_id' => $connector->id, 'url' => 'https://ok.example.test/a', 'method' => 'POST', 'is_active' => true, 'retry_attempts' => 1, 'timeout_seconds' => 5]);
    WebhookEndpoint::create(['connector_id' => $connector->id, 'url' => 'https://ok.example.test/b', 'method' => 'POST', 'is_active' => true, 'retry_attempts' => 1, 'timeout_seconds' => 5]);
    WebhookEndpoint::create(['connector_id' => $connector->id, 'url' => 'https://fail.example.test/c', 'method' => 'POST', 'is_active' => true, 'retry_attempts' => 1, 'timeout_seconds' => 5]);

    $response = test()->actingAs($user, 'sanctum')->postJson("/api/v1/integration/connectors/{$connector->id}/dispatch", [
        'payload' => ['event' => 'test.multi'],
    ]);

    $response->assertCreated();
    expect($response->json('status'))->toBe('partial')
        ->and($response->json('records_processed'))->toBe(2)
        ->and($response->json('records_failed'))->toBe(1);
});

// ---------------------------------------------------------------------------
// 10. WhbPartnerService approve/reject/suspend — defense-in-depth guard
// ---------------------------------------------------------------------------

test('WhbPartnerService::approveConnection() with an explicit tenantId refuses to touch another tenant\'s connection', function () {
    $companyA = Company::factory()->create();
    $connection = WhbConnection::create([
        'local_tenant_id' => (string) $companyA->id, 'connection_type' => 'local', 'status' => 'pending',
    ]);

    expect(fn () => app(WhbPartnerService::class)->approveConnection($connection->id, 1, [], 'not-the-owner'))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

// ---------------------------------------------------------------------------
// Re-confirmation: pre-existing IDOR fixes still hold after everything since
// ---------------------------------------------------------------------------

test('re-confirmed: company A cannot view/activate/dispatch through company B\'s generic connector', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier326User($companyA, 'admin');

    $connectorB = IntegrationConnector::create([
        'tenant_id' => (string) $companyB->id, 'name' => 'B Connector', 'slug' => 'b-connector',
        'provider_type' => 'webhook', 'status' => 'inactive', 'created_by' => 1,
    ]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/integration/connectors/{$connectorB->id}")->assertForbidden();
    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/integration/connectors/{$connectorB->id}/activate")->assertForbidden();
    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/integration/connectors/{$connectorB->id}/dispatch", ['payload' => []])->assertForbidden();
    expect($connectorB->fresh()->status)->toBe('inactive');
});

test('re-confirmed: company A cannot approve/reject/suspend company B\'s WHB federation partner connection', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $userA = chantier326User($companyA, 'admin');

    $connectionB = WhbConnection::create([
        'local_tenant_id' => (string) $companyB->id, 'connection_type' => 'local', 'status' => 'pending',
    ]);

    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/whb/connections/{$connectionB->id}/approve", ['permissions' => []])->assertStatus(404);
    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/whb/connections/{$connectionB->id}/reject")->assertStatus(404);
    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/whb/connections/{$connectionB->id}/suspend")->assertStatus(404);
    expect($connectionB->fresh()->status)->toBe('pending');
});

test('re-confirmed: the /v1/whb group still requires admin/super-admin, not any authenticated role', function () {
    $company = Company::factory()->create();
    $user = chantier326User($company, 'employee');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/whb/connections')->assertForbidden();
});

// ---------------------------------------------------------------------------
// Re-confirmation: federation HMAC signature protection still intact
// ---------------------------------------------------------------------------

test('re-confirmed: a genuinely signed federation request still succeeds', function () {
    $secret = app(WhbFederationService::class)->generateSecret();
    $connection = WhbConnection::create([
        'local_tenant_id' => 'default', 'remote_server_url' => 'https://partner.chantier326.test',
        'connection_type' => 'remote', 'status' => 'active', 'shared_secret' => encrypt($secret),
    ]);

    $body = json_encode(['new_token' => 'real-signed-token']);
    $timestamp = time();
    $signature = app(WhbFederationService::class)->sign($body, $secret, $timestamp);

    $response = test()->call('POST', '/api/v1/federation/refresh', [], [], [], [
        'HTTP_X-WH-Instance'  => 'https://partner.chantier326.test',
        'HTTP_X-WH-Signature' => $signature,
        'HTTP_X-WH-Timestamp' => (string) $timestamp,
        'CONTENT_TYPE'        => 'application/json',
    ], $body);

    $response->assertOk();
    expect(decrypt($connection->fresh()->getRawOriginal('session_token')))->toBe('real-signed-token');
});

test('re-confirmed: a forged/unsigned federation request is still rejected', function () {
    $response = test()->postJson('/api/v1/federation/refresh', ['new_token' => 'forged']);

    $response->assertStatus(401);
});
