<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Logistics\Models\Shipment;

/**
 * Chantier 19 Lot 4 (Logistics) re-audit via empirical execution.
 *
 * Headline finding: CustomsDeclarationController (routes/api.php's
 * `logistics/customs-declarations` group — the real, live path
 * Customs/Index.vue actually calls) had zero company/tenant scoping of any
 * kind on any method — confirmed empirically via a real cross-company HTTP
 * request that any authenticated Logistics user of any company could list,
 * view, edit, delete, and submit every other company's customs
 * declarations. Fixed by scoping every method through the real `tenant_id`
 * column on logistics_customs_declarations (this table has no `company_id`
 * column at all, unlike lgx_delivery_routes/lgx_vehicles).
 *
 * A second, independent instance of the same model was found broken in a
 * different way: CustomsRouteController's customs*() methods (a separate,
 * unreachable-from-any-UI-page route group at `logistics/customs`, not
 * `logistics/customs-declarations`) filtered `CustomsDeclaration` on a
 * `company_id` column that has never existed on this table — SQLite's
 * lenient double-quoted-identifier fallback silently returned 0 rows
 * instead of throwing (the exact camouflage pattern already documented for
 * Shared\CountryController in Chantier 10 Socle), while a real MySQL
 * production deployment would hard-crash on the same call. Fixed to the
 * real `tenant_id` column, and to a real `?? 0` empty-bucket fallback
 * instead of the shared-bucket-collision `?? 1` anti-pattern (which would
 * have collapsed every company-less user into the real seeded company
 * id=1's own real data). A THIRD, independent bug on the very same
 * unreachable endpoint set was also confirmed and fixed: customsIndex()/
 * customsShow() eager-loaded a `items` relation that has never existed on
 * CustomsDeclaration (confirmed via RelationNotFoundException, not a
 * hypothetical) — a guaranteed fatal error on every real call regardless of
 * the tenant bug; createDeclaration() was fixed the same way (it
 * unconditionally ->load('items') on every return). calculateDuties()'s own
 * deeper per-item duty-calculation design (needs a real
 * CustomsDeclarationItem schema this session has no spec for) is left as a
 * documented gap — see this pass's final report.
 *
 * Also fixed: DeliveryRoute/RouteStop (the 3 tables Chantier 8.3il part 4
 * added, claimed as "the only way to ever create a DeliveryRoute via the
 * API") had, in the actual current routes/api.php, ZERO route registered to
 * routesStore()/routesIndex() at all — confirmed via `php artisan
 * route:list` + a repo-wide grep, contradicting that earlier documentation.
 * This meant routesOptimize()/routesStart()/routeStopComplete()/
 * routesComplete() (all 4 already routed) could never actually be reached
 * in practice, since no DeliveryRoute could ever exist to operate on.
 * Registered under a new, non-colliding `logistics/delivery-routes` prefix.
 */
if (! function_exists('seedLogisticsRolesChantier19Lot4')) {
    function seedLogisticsRolesChantier19Lot4(\Tests\TestCase $test): void
    {
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $test->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
    }
}

describe('Chantier 19 Lot 4 — Logistics CustomsDeclarationController tenant isolation', function () {
    test('a customs declaration created by one company is invisible to another company', function () {
        seedLogisticsRolesChantier19Lot4($this);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userA->assignRole('admin');
        $userB = User::factory()->create(['company_id' => $companyB->id]);
        $userB->assignRole('admin');

        $shipment = Shipment::factory()->create();

        $store = $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/logistics/customs-declarations', [
                'shipment_id' => $shipment->id,
                'declared_value' => 1000,
                'currency' => 'MGA',
            ]);
        $store->assertStatus(201);
        $declarationId = $store->json('data.id');
        expect($store->json('data.tenant_id'))->toBe($companyA->id);

        // Confirmed empirically before the fix: index()/show()/update()/
        // destroy()/submit() all had zero tenant scoping — any authenticated
        // Logistics user of any company could see/edit/delete/submit any
        // other company's customs declaration.
        $listB = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/logistics/customs-declarations');
        $listB->assertStatus(200);
        expect(collect($listB->json('data'))->pluck('id'))->not->toContain($declarationId);

        $this->actingAs($userB, 'sanctum')
            ->getJson("/api/v1/logistics/customs-declarations/{$declarationId}")
            ->assertStatus(404);

        $this->actingAs($userB, 'sanctum')
            ->putJson("/api/v1/logistics/customs-declarations/{$declarationId}", ['notes' => 'hijacked'])
            ->assertStatus(404);

        $this->actingAs($userB, 'sanctum')
            ->postJson("/api/v1/logistics/customs-declarations/{$declarationId}/submit")
            ->assertStatus(404);

        $this->actingAs($userB, 'sanctum')
            ->deleteJson("/api/v1/logistics/customs-declarations/{$declarationId}")
            ->assertStatus(404);

        // Company A can still fully operate on its own declaration.
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/logistics/customs-declarations/{$declarationId}")
            ->assertStatus(200);

        $this->actingAs($userA, 'sanctum')
            ->postJson("/api/v1/logistics/customs-declarations/{$declarationId}/submit")
            ->assertStatus(200)
            ->assertJsonPath('status', 'submitted');
    });
});

describe('Chantier 19 Lot 4 — Logistics CustomsRouteController phantom-column + missing-route fixes', function () {
    test('customsIndex() no longer 500s / silently no-ops against a phantom company_id column', function () {
        seedLogisticsRolesChantier19Lot4($this);
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('admin');

        $shipment = Shipment::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/customs', [
            'type' => 'import',
            'country_of_origin' => 'CN',
            'country_of_destination' => 'MG',
        ])->assertStatus(201);

        // Before the fix this always returned 0 results (SQLite silently
        // treats the nonexistent "company_id" double-quoted identifier as a
        // string literal rather than throwing) regardless of real data —
        // now genuinely scoped by the real `tenant_id` column.
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/logistics/customs');
        $response->assertStatus(200);
        expect($response->json('total'))->toBeGreaterThanOrEqual(1);
    });

    test('the 3 missing Logistics tables (lgx_delivery_routes/lgx_route_stops) now have a real, reachable create path', function () {
        seedLogisticsRolesChantier19Lot4($this);
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/delivery-routes', [
            'name' => 'Tournée Antananarivo Nord',
            'date' => now()->addDay()->toDateString(),
            'stops' => [
                ['address' => '12 rue Rainizanabololona', 'lat' => -18.879, 'lng' => 47.507],
                ['address' => '5 avenue de l\'Indépendance', 'lat' => -18.906, 'lng' => 47.524],
            ],
        ]);

        $response->assertStatus(201);
        expect($response->json('company_id'))->toBe($company->id);
        expect($response->json('stops'))->toHaveCount(2);

        $listResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/logistics/delivery-routes');
        $listResponse->assertStatus(200);
        expect(collect($listResponse->json('data'))->pluck('id'))->toContain($response->json('id'));

        // The already-routed lifecycle endpoints this create path unblocks.
        $routeId = $response->json('id');
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/logistics/routes/{$routeId}/optimize")
            ->assertStatus(200);
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/logistics/routes/{$routeId}/start")
            ->assertStatus(200)
            ->assertJsonPath('status', 'in_progress');
    });
});
