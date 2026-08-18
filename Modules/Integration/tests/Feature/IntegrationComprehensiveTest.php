<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Integration\Models\Integration;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Models\IntegrationSyncLog;
use Modules\Integration\Models\SyncLog;
use Modules\Integration\Models\WebhookEndpoint;
use Modules\Integration\Services\IntegrationService;
use Modules\Integration\Services\IntegrationManager;

uses(RefreshDatabase::class);

// ─── IntegrationService ───────────────────────────────────────────────────────

describe('IntegrationService - Connectors', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(IntegrationService::class);
    });

    test('can create a connector', function () {
        $connector = $this->service->createConnector([
            'name'          => 'Shopify Test',
            'provider_type' => 'shopify',
            'config'        => ['shop_url' => 'test.myshopify.com'],
            'tenant_id'     => (int) $this->user->id,
            'created_by'    => $this->user->id,
        ]);

        expect($connector)->toBeInstanceOf(IntegrationConnector::class)
            ->and($connector->name)->toBe('Shopify Test');
    });

    test('can activate a connector', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->id,
            'status'    => 'inactive',
        ]);

        $activated = $this->service->activateConnector($connector);

        expect($activated->status)->toBe('active');
    });

    test('can dispatch a webhook for a connector', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->id,
        ]);

        $log = $this->service->dispatchWebhook($connector, ['event' => 'test.event', 'data' => []]);

        expect($log)->toBeInstanceOf(SyncLog::class);
    });

    test('can log a sync operation', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->id,
        ]);

        $log = $this->service->logSync($connector, 'inbound', [
            'status'         => 'success',
            'records_synced' => 10,
        ]);

        expect($log)->toBeInstanceOf(SyncLog::class)
            ->and($log->direction)->toBe('inbound');
    });

    test('can get connector stats for a tenant', function () {
        IntegrationConnector::factory()->count(3)->create([
            'tenant_id' => (string) $this->user->id,
        ]);

        $stats = $this->service->getConnectorStats((int) $this->user->id);

        expect($stats)->toBeArray();
    });
});

// ─── IntegrationManager ───────────────────────────────────────────────────────

describe('IntegrationManager', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->manager = app(IntegrationManager::class);
    });

    test('manager is resolvable from container', function () {
        $manager = app(IntegrationManager::class);
        expect($manager)->toBeInstanceOf(IntegrationManager::class);
    });

    test('manager has expected interface methods', function () {
        $manager = app(IntegrationManager::class);
        // Verify the manager has key methods for connector management
        expect(
            method_exists($manager, 'resolveConnector') ||
            method_exists($manager, 'connect') ||
            method_exists($manager, 'getAvailable')
        )->toBeTrue();
    });
});

// ─── API Endpoints — Connectors ───────────────────────────────────────────────

describe('Integration API - Connectors', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        // Chantier 8.6: IntegrationController/IntegrationConnectorPolicy now
        // scope connectors by $user->company_id (fixing a real IDOR — see
        // CLAUDE.md) instead of the connector-creator's own user id. Give
        // the test user a real company_id (users.company_id is a real FK to
        // companies) and create fixture connectors under that same tenant
        // so the policy's ownership check passes.
        $company = \App\Models\Company::factory()->create();
        $this->user->forceFill(['company_id' => $company->id])->save();
    });

    test('can list connectors', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/integration/connectors')
            ->assertOk();
    });

    test('can create a connector via API', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/integration/connectors', [
                'name'          => 'API Connector',
                'provider_type' => 'webhook',
                'config'        => ['url' => 'https://example.com/webhook'],
            ])
            ->assertCreated();
    });

    test('can view a connector via API', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->company_id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/integration/connectors/{$connector->id}")
            ->assertOk();
    });

    test('can activate a connector via API', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->company_id,
            'status'    => 'inactive',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/integration/connectors/{$connector->id}/activate")
            ->assertOk();
    });

    test('can add a webhook to a connector via API', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->company_id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/integration/connectors/{$connector->id}/webhook", [
                'url'    => 'https://example.com/hook',
                'events' => ['order.created'],
            ])
            ->assertCreated();
    });

    test('can dispatch to a connector via API', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->company_id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/integration/connectors/{$connector->id}/dispatch", [
                'event'   => 'test.ping',
                'payload' => ['key' => 'value'],
            ])
            ->assertCreated();
    });

    test('can get connector logs via API', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->company_id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/integration/connectors/{$connector->id}/logs")
            ->assertOk();
    });

    test('can get integration stats via API', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/integration/stats')
            ->assertOk();
    });
});

test('unauthenticated user cannot access connectors', function () {
    $this->getJson('/api/v1/integration/connectors')
        ->assertUnauthorized();
});

// ─── API Endpoints — Backend Status ──────────────────────────────────────────

describe('Integration API - Backend Status', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can check Supabase status', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/integration/supabase/status')
            ->assertOk();
    });

    test('can check Firebase status', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/integration/firebase/status')
            ->assertOk();
    });
});

// ─── Models ───────────────────────────────────────────────────────────────────

describe('Integration Models', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('Integration factory creates valid model', function () {
        $integration = Integration::factory()->create([
            'tenant_id' => (string) $this->user->id,
        ]);
        expect($integration->id)->not->toBeNull();
    });

    test('IntegrationConnector factory creates valid model', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->id,
        ]);
        expect($connector->id)->not->toBeNull()
            ->and($connector->name)->not->toBeEmpty();
    });

    test('SyncLog factory creates valid model', function () {
        $log = SyncLog::factory()->create();
        expect($log->id)->not->toBeNull();
    });

    test('IntegrationSyncLog factory creates valid model', function () {
        $syncLog = IntegrationSyncLog::factory()->create();
        expect($syncLog->id)->not->toBeNull();
    });

    test('WebhookEndpoint factory creates valid model', function () {
        $endpoint = WebhookEndpoint::factory()->create();
        expect($endpoint->id)->not->toBeNull();
    });

    test('connector belongs to a tenant', function () {
        $connector = IntegrationConnector::factory()->create([
            'tenant_id' => (string) $this->user->id,
        ]);

        expect($connector->tenant_id)->toBe((string) $this->user->id);
    });
});
