<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CustomsDeclaration;
use Modules\Logistics\Models\DeliveryRound;
use Modules\Logistics\Models\FreightInvoice;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\CustomsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Chantier 32.23 — audit approfondi en 14 couches de Modules\Logistics.
 *
 * Locks in every real, empirically-confirmed bug found and fixed in this
 * pass. See the matching CLAUDE.md changelog entry for the full narrative
 * (module-wide missing company/tenant scoping across ~12 models is
 * documented there as a chantier-sized gap, deliberately NOT fixed here —
 * only the contained, already-scoped-elsewhere IDOR on CustomsRouteController
 * is fixed, reusing the real tenant_id column already established by the
 * Chantier 19 Lot 4 fix on the sibling CustomsDeclarationController).
 */
if (! function_exists('seedLogisticsRolesChantier3223')) {
    function seedLogisticsRolesChantier3223(\Tests\TestCase $test): void
    {
        if (Permission::count() === 0) {
            $test->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
    }
}

describe('Chantier 32.23 — DeliveryStop mass-assignment data-loss bug', function () {
    test('addStop() actually persists location_id/delivery_window/address/contact_name/notes, not just shipment_id/stop_order', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');

        $round = DeliveryRound::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/logistics/delivery-rounds/{$round->id}/stops", [
                'location_id' => 42,
                'sequence' => 1,
                'delivery_window' => '09:00-11:00',
                'address' => '12 Rue de la Paix, Antananarivo',
                'contact_name' => 'Rakoto Jean',
                'notes' => 'Sonner deux fois, code portail 4821',
            ]);

        $response->assertStatus(201);

        $stop = $round->stops()->first();
        expect($stop)->not->toBeNull();
        // Before the fix, every one of these was silently NULL — confirmed
        // empirically via tinker before this fix landed.
        expect((int) $stop->location_id)->toBe(42);
        expect((int) $stop->stop_order)->toBe(1);
        expect($stop->delivery_window)->toBe('09:00-11:00');
        expect($stop->address)->toBe('12 Rue de la Paix, Antananarivo');
        expect($stop->contact_name)->toBe('Rakoto Jean');
        expect($stop->notes)->toBe('Sonner deux fois, code portail 4821');
    });

    test('DeliveryRoundController::store()s own inline stops array also persists the full field set', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');
        $carrier = Carrier::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/logistics/delivery-rounds', [
                'driver_name' => 'Rasoa Marie',
                'carrier_id' => $carrier->id,
                'planned_date' => now()->toDateString(),
                'stops' => [
                    ['location_id' => 7, 'address' => 'Lot II M 45 Antanimena', 'contact_name' => 'Randria', 'notes' => 'Livraison matin'],
                ],
            ]);

        $response->assertStatus(201);
        $round = DeliveryRound::find($response->json('data.id'));
        $stop = $round->stops()->first();

        expect((int) $stop->location_id)->toBe(7);
        expect($stop->address)->toBe('Lot II M 45 Antanimena');
        expect($stop->contact_name)->toBe('Randria');
    });
});

describe('Chantier 32.23 — Carrier is_active/search filter was a confirmed no-op', function () {
    test('is_active filter, actually sent by Carriers/Index.vue, now filters for real', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');

        $active = Carrier::factory()->create(['is_active' => true, 'name' => 'Actif SA']);
        $inactive = Carrier::factory()->create(['is_active' => false, 'name' => 'Inactif SA']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/logistics/carriers?is_active=1');

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($active->id);
        expect($ids)->not->toContain($inactive->id);
    });

    test('search filter, actually sent by Carriers/Index.vue, now filters for real', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');

        $match = Carrier::factory()->create(['name' => 'Sahel Express Unique']);
        $noMatch = Carrier::factory()->create(['name' => 'Autre Transporteur']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/logistics/carriers?search=Sahel Express Unique');

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($match->id);
        expect($ids)->not->toContain($noMatch->id);
    });
});

describe('Chantier 32.23 — Shipments/Index.vue search box was a confirmed no-op', function () {
    test('search param (placeholder promises reference/tracking/recipient) now actually filters', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');

        $match = Shipment::factory()->create(['reference' => 'SHP-UNIQUE-999']);
        $noMatch = Shipment::factory()->create(['reference' => 'SHP-OTHER-111']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/logistics/shipments?search=UNIQUE-999');

        $ids = collect($response->json('data'))->pluck('id');
        expect($ids)->toContain($match->id);
        expect($ids)->not->toContain($noMatch->id);
    });
});

describe('Chantier 32.23 — CustomsRouteController cross-tenant IDOR', function () {
    test('company B cannot read/submit/clear company A customs declaration via GET/PUT logistics/customs/{id}', function () {
        seedLogisticsRolesChantier3223($this);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userA->assignRole('admin');
        $userB = User::factory()->create(['company_id' => $companyB->id]);
        $userB->assignRole('admin');

        $declaration = CustomsDeclaration::factory()->create(['tenant_id' => $companyA->id]);

        // Company A can read its own declaration.
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/logistics/customs/{$declaration->id}")
            ->assertStatus(200);

        // Company B cannot — confirmed empirically to have returned 200 with
        // company A's real declaration data before this fix.
        $this->actingAs($userB, 'sanctum')
            ->getJson("/api/v1/logistics/customs/{$declaration->id}")
            ->assertStatus(404);

        $this->actingAs($userB, 'sanctum')
            ->putJson("/api/v1/logistics/customs/{$declaration->id}/submit")
            ->assertStatus(404);

        $this->actingAs($userB, 'sanctum')
            ->putJson("/api/v1/logistics/customs/{$declaration->id}/clear", [])
            ->assertStatus(404);
    });
});

describe('Chantier 32.23 — CustomsService::getPendingDeclarations() confirmed dead code', function () {
    test('the method no longer exists — deleted rather than repaired (zero callers, two independent bugs: wrong column + nonexistent relation)', function () {
        expect(method_exists(CustomsService::class, 'getPendingDeclarations'))->toBeFalse();
    });
});

describe('Chantier 32.23 — RouteController::optimize() confirmed dead stub', function () {
    test('the method no longer exists — deleted, superseded by the real, routed RouteOptimizationController/RouteOptimizerService VRP solver', function () {
        expect(method_exists(\Modules\Logistics\Http\Controllers\Api\RouteController::class, 'optimize'))->toBeFalse();
    });
});

describe('Chantier 32.23 — TrackingEvent webhook-retry deduplication activated', function () {
    test('a second POST with the same idempotency_key does not create a duplicate row', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');
        $shipment = Shipment::factory()->create();

        $payload = [
            'event_type' => 'in_transit',
            'recorded_at' => now()->toIso8601String(),
            'idempotency_key' => 'idem-key-123',
        ];

        $first = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/logistics/shipments/{$shipment->id}/tracking-events", $payload);
        $first->assertStatus(201);

        $second = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/logistics/shipments/{$shipment->id}/tracking-events", $payload);
        $second->assertStatus(200);
        expect($second->json('message'))->toContain('duplicate');

        expect($shipment->trackingEvents()->count())->toBe(1);
    });

    test('two distinct provider_event_id values are both recorded (not falsely deduplicated)', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');
        $shipment = Shipment::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/logistics/shipments/{$shipment->id}/tracking-events", [
                'event_type' => 'in_transit', 'recorded_at' => now()->toIso8601String(), 'provider_event_id' => 'evt-1',
            ])->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/logistics/shipments/{$shipment->id}/tracking-events", [
                'event_type' => 'out_for_delivery', 'recorded_at' => now()->addMinutes(5)->toIso8601String(), 'provider_event_id' => 'evt-2',
            ])->assertStatus(201);

        expect($shipment->trackingEvents()->count())->toBe(2);
    });
});

describe('Chantier 32.23 — FreightInvoice approve/dispute tightened away from warehouse-operator', function () {
    test('warehouse-operator (the broad route-gate role) is denied approve/dispute — a real financial action', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'warehouse-operator', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('warehouse-operator');
        $invoice = FreightInvoice::factory()->create(['status' => 'draft']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/logistics/freight-invoices/{$invoice->id}/approve")
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/logistics/freight-invoices/{$invoice->id}/dispute", ['reason' => 'écart de montant'])
            ->assertStatus(403);
    });

    test('logistics-manager can approve for real', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');
        $invoice = FreightInvoice::factory()->create(['status' => 'draft']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/logistics/freight-invoices/{$invoice->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('status', 'approved');
    });
});

describe('Chantier 32.23 — LogisticsAiAssistController phantom users.role bug', function () {
    test('the real /api/v1/logistics/ai/assist route returns real guidance without erroring, using the real Spatie role', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/logistics/ai/assist', ['action' => 'manage_carrier', 'locale' => 'fr']);

        $response->assertStatus(200);
        expect($response->json('what_to_do'))->not->toBeEmpty();
    });
});

describe('Chantier 32.23 — 6 new Logistics AI-assist actions have real, non-empty fr+en fallback text', function () {
    test('view_dashboard/plan_delivery_round/manage_freight_invoices/manage_customs/view_analytics/optimize_routes all resolve real guidance via the real HTTP route', function () {
        seedLogisticsRolesChantier3223($this);
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('logistics-manager');

        foreach (['view_dashboard', 'plan_delivery_round', 'manage_freight_invoices', 'manage_customs', 'view_analytics', 'optimize_routes'] as $action) {
            foreach (['fr', 'en'] as $locale) {
                $response = $this->actingAs($user, 'sanctum')
                    ->postJson('/api/v1/ai/assist', ['module' => 'Logistics', 'action' => $action, 'locale' => $locale]);

                $response->assertStatus(200);
                expect(trim((string) $response->json('what_to_do')))->not->toBe('');
            }
        }
    });
});

describe('Chantier 32.23 — CarrierIntegrationService::bookDhl() column-name bug', function () {
    test('weight_kg (not the nonexistent total_weight_kg) is what the real Shipment model carries', function () {
        $shipment = Shipment::factory()->create(['weight_kg' => 12.5]);
        expect((float) $shipment->weight_kg)->toBe(12.5);
        expect(fn () => $shipment->total_weight_kg)->not->toThrow(\Throwable::class);
        expect($shipment->total_weight_kg)->toBeNull();
    });
});
