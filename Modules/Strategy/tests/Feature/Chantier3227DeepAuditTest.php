<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Strategy\Models\StrategyKpi;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyRitual;
use Modules\Strategy\Models\StrategyRitualSession;
use Modules\Strategy\Models\StrategyScenario;
use Modules\Strategy\Models\StrategySignal;
use App\Models\Company;
use App\Models\User;

uses(RefreshDatabase::class);

/**
 * Chantier 32.27 — audit 14 couches de Modules\Strategy. Verrouille par de
 * vraies requêtes HTTP les corrections empiriques de ce chantier :
 *   1. KPIRegistryService/StrategyRatioService — le calcul des ratios
 *      Strategy First ignorait le cloisonnement par société.
 *   2. AlignmentCascadeService::getCascadeMap() — même fuite indépendante
 *      sur la carte de cascade.
 *   3. StrategyPlanController — show()/tree()/duplicate()/health() sans
 *      vérification de propriété.
 *   4. KpiController::values()/refresh() — aucun authorize()/cloisonnement.
 *   5. RitualController — update()/sessions()/createSession()/
 *      startSession()/completeSession() sans vérification de propriété.
 *   6. ScenarioController — show()/update()/addAssumption()/
 *      updateAssumption()/impact()/compare() sans vérification de propriété.
 *   7. SignalController::markRead()/dismiss() — même trou.
 *   8. StrategyAdvisorController::insights()/boardReport()/ritualSummary()
 *      — exists: valide l'existence mais jamais la propriété.
 *   9. 5 méthodes apiResource inexistantes (KpiController::show(),
 *      RitualController::show()/destroy(), ScenarioController::destroy(),
 *      OkrController::show()) — erreur fatale garantie avant correctif.
 */
function strategyUser(?int $companyId = null): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company = $companyId ? Company::find($companyId) : Company::factory()->create();

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('admin');

    return $user;
}

// ─── 1+2. KPIRegistryService / AlignmentCascadeService tenant blending ───────

describe('Chantier 32.27 — KPIRegistryService/cascade real tenant scoping', function () {
    test('the ratios endpoint computes current_value from the caller\'s own company data only', function () {
        $userA = strategyUser();
        $userB = strategyUser();

        DB::table('crm_leads')->insert([
            ['tenant_id' => (string) $userA->company_id, 'first_name' => 'a1', 'last_name' => 'a1', 'status' => 'converted', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => (string) $userA->company_id, 'first_name' => 'a2', 'last_name' => 'a2', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
            // Company B: 4 leads, 0 converted — a different rate from A's 50%.
            ['tenant_id' => (string) $userB->company_id, 'first_name' => 'b1', 'last_name' => 'b1', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => (string) $userB->company_id, 'first_name' => 'b2', 'last_name' => 'b2', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => (string) $userB->company_id, 'first_name' => 'b3', 'last_name' => 'b3', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => (string) $userB->company_id, 'first_name' => 'b4', 'last_name' => 'b4', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $ratioA = collect(
            test()->actingAs($userA, 'sanctum')->getJson('/api/v1/strategy/ratios')->json('data')
        )->firstWhere('key', 'lead_conversion_rate');

        $ratioB = collect(
            test()->actingAs($userB, 'sanctum')->getJson('/api/v1/strategy/ratios')->json('data')
        )->firstWhere('key', 'lead_conversion_rate');

        expect((float) $ratioA['current_value'])->toBe(50.0)
            ->and((float) $ratioB['current_value'])->toBe(0.0);
    });

    test('the cascade endpoint never mixes another company\'s real strategic objectives', function () {
        $userA = strategyUser();
        $userB = strategyUser();

        $planA = StrategyPlan::create(['tenant_id' => (string) $userA->company_id, 'name' => 'Plan A', 'period_start' => 2026, 'period_end' => 2027, 'status' => 'active']);
        $planB = StrategyPlan::create(['tenant_id' => (string) $userB->company_id, 'name' => 'Plan B SECRET', 'period_start' => 2026, 'period_end' => 2027, 'status' => 'active']);
        StrategyObjective::create(['plan_id' => $planA->id, 'level' => 'strategic', 'title' => 'Objective A', 'status' => 'active', 'progress' => 10]);
        StrategyObjective::create(['plan_id' => $planB->id, 'level' => 'strategic', 'title' => 'SECRET Objective B', 'status' => 'active', 'progress' => 20]);

        $titlesA = collect(
            test()->actingAs($userA, 'sanctum')->getJson('/api/v1/strategy/cascade')->json('data.nodes')
        )->pluck('title');

        expect($titlesA)->toContain('Objective A')
            ->not->toContain('SECRET Objective B');
    });
});

// ─── 3. StrategyPlanController by-id actions ─────────────────────────────────

describe('Chantier 32.27 — StrategyPlanController tenant ownership', function () {
    test('show()/tree()/duplicate()/health() all 404 on another company\'s plan', function () {
        $userA = strategyUser();
        $foreignPlan = StrategyPlan::factory()->create(); // random unrelated tenant_id

        $client = test()->actingAs($userA, 'sanctum');

        $client->getJson("/api/v1/strategy/plans/{$foreignPlan->id}")->assertForbidden();
        $client->getJson("/api/v1/strategy/plans/{$foreignPlan->id}/tree")->assertStatus(404);
        $client->postJson("/api/v1/strategy/plans/{$foreignPlan->id}/duplicate", ['name' => 'Stolen copy'])->assertStatus(404);
        $client->getJson("/api/v1/strategy/plans/{$foreignPlan->id}/health")->assertStatus(404);
    });

    test('duplicate() still works on the caller\'s own plan', function () {
        $user = strategyUser();
        $plan = StrategyPlan::factory()->create(['tenant_id' => (string) $user->company_id]);

        test()->actingAs($user, 'sanctum')
            ->postJson("/api/v1/strategy/plans/{$plan->id}/duplicate", ['name' => 'My real copy'])
            ->assertCreated()
            ->assertJsonPath('name', 'My real copy');
    });
});

// ─── 4. KpiController ─────────────────────────────────────────────────────────

describe('Chantier 32.27 — KpiController show/values/refresh ownership', function () {
    test('show()/values()/refresh() all reject another company\'s KPI', function () {
        $userA = strategyUser();
        $userB = strategyUser();
        $kpiB = StrategyKpi::create(['tenant_id' => (string) $userB->company_id, 'name' => 'Secret KPI B']);

        $client = test()->actingAs($userA, 'sanctum');

        $client->getJson("/api/v1/strategy/kpis/{$kpiB->id}")->assertForbidden();
        $client->getJson("/api/v1/strategy/kpis/{$kpiB->id}/values")->assertForbidden();
        $client->getJson("/api/v1/strategy/kpis/{$kpiB->id}/refresh")->assertForbidden();
    });

    test('show() returns the real KPI definition for the caller\'s own KPI', function () {
        $user = strategyUser();
        $kpi = StrategyKpi::create(['tenant_id' => (string) $user->company_id, 'name' => 'My KPI']);

        test()->actingAs($user, 'sanctum')
            ->getJson("/api/v1/strategy/kpis/{$kpi->id}")
            ->assertOk()
            ->assertJsonPath('name', 'My KPI');
    });
});

// ─── 5. RitualController ──────────────────────────────────────────────────────

describe('Chantier 32.27 — RitualController show/update/destroy/sessions ownership', function () {
    test('show()/update()/destroy() and session actions all 404 on another company\'s ritual', function () {
        $userA = strategyUser();
        $userB = strategyUser();
        $ritualB = StrategyRitual::create(['tenant_id' => (string) $userB->company_id, 'name' => 'Secret ritual', 'type' => 'weekly_checkin', 'cadence' => 'weekly', 'is_active' => true]);
        $sessionB = StrategyRitualSession::create(['ritual_id' => $ritualB->id, 'scheduled_at' => now()]);

        $client = test()->actingAs($userA, 'sanctum');

        $client->getJson("/api/v1/strategy/rituals/{$ritualB->id}")->assertStatus(404);
        $client->putJson("/api/v1/strategy/rituals/{$ritualB->id}", ['name' => 'Hijacked'])->assertStatus(404);
        $client->getJson("/api/v1/strategy/rituals/{$ritualB->id}/sessions")->assertStatus(404);
        $client->postJson("/api/v1/strategy/rituals/{$ritualB->id}/sessions")->assertStatus(404);
        $client->putJson("/api/v1/strategy/rituals/sessions/{$sessionB->id}/start")->assertStatus(404);
        $client->putJson("/api/v1/strategy/rituals/sessions/{$sessionB->id}/complete", [])->assertStatus(404);
        $client->deleteJson("/api/v1/strategy/rituals/{$ritualB->id}")->assertStatus(404);

        // Confirm it was NOT deleted despite the 404.
        expect(StrategyRitual::find($ritualB->id))->not->toBeNull();
    });

    test('show()/update()/destroy() work end-to-end on the caller\'s own ritual', function () {
        $user = strategyUser();
        $ritual = StrategyRitual::create(['tenant_id' => (string) $user->company_id, 'name' => 'My ritual', 'type' => 'weekly_checkin', 'cadence' => 'weekly', 'is_active' => true]);

        $client = test()->actingAs($user, 'sanctum');

        $client->getJson("/api/v1/strategy/rituals/{$ritual->id}")->assertOk()->assertJsonPath('name', 'My ritual');
        $client->putJson("/api/v1/strategy/rituals/{$ritual->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('name', 'Renamed');
        $client->deleteJson("/api/v1/strategy/rituals/{$ritual->id}")->assertOk();

        expect(StrategyRitual::find($ritual->id))->toBeNull();
    });
});

// ─── 6. ScenarioController ────────────────────────────────────────────────────

describe('Chantier 32.27 — ScenarioController show/update/destroy/compare ownership', function () {
    test('show()/update()/destroy()/addAssumption()/impact() all 404 on another company\'s scenario', function () {
        $userA = strategyUser();
        $userB = strategyUser();
        $scenarioB = StrategyScenario::create(['tenant_id' => (string) $userB->company_id, 'name' => 'Secret scenario']);

        $client = test()->actingAs($userA, 'sanctum');

        $client->getJson("/api/v1/strategy/scenarios/{$scenarioB->id}")->assertStatus(404);
        $client->putJson("/api/v1/strategy/scenarios/{$scenarioB->id}", ['name' => 'Hijacked'])->assertStatus(404);
        $client->postJson("/api/v1/strategy/scenarios/{$scenarioB->id}/assumptions", [
            'variable_name' => 'x', 'base_value' => 1, 'adjusted_value' => 2,
        ])->assertStatus(404);
        $client->getJson("/api/v1/strategy/scenarios/{$scenarioB->id}/impact")->assertStatus(404);
        $client->deleteJson("/api/v1/strategy/scenarios/{$scenarioB->id}")->assertStatus(404);

        expect(StrategyScenario::find($scenarioB->id))->not->toBeNull();
    });

    test('compare() rejects a batch containing another company\'s scenario id', function () {
        $userA = strategyUser();
        $userB = strategyUser();
        $scenarioA = StrategyScenario::create(['tenant_id' => (string) $userA->company_id, 'name' => 'Mine']);
        $scenarioB = StrategyScenario::create(['tenant_id' => (string) $userB->company_id, 'name' => 'Not mine']);

        test()->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/strategy/scenarios/compare', ['scenario_ids' => [$scenarioA->id, $scenarioB->id]])
            ->assertStatus(404);
    });

    test('destroy() works end-to-end on the caller\'s own scenario', function () {
        $user = strategyUser();
        $scenario = StrategyScenario::create(['tenant_id' => (string) $user->company_id, 'name' => 'Mine']);

        test()->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/strategy/scenarios/{$scenario->id}")
            ->assertOk();

        expect(StrategyScenario::find($scenario->id))->toBeNull();
    });
});

// ─── 7. SignalController ──────────────────────────────────────────────────────

describe('Chantier 32.27 — SignalController markRead/dismiss ownership', function () {
    test('markRead()/dismiss() 404 on another company\'s signal', function () {
        $userA = strategyUser();
        $userB = strategyUser();
        $signalB = StrategySignal::create([
            'tenant_id' => (string) $userB->company_id, 'type' => 'warning', 'title' => 'Secret signal',
            'detected_at' => now(),
        ]);

        $client = test()->actingAs($userA, 'sanctum');

        $client->putJson("/api/v1/strategy/signals/{$signalB->id}/read")->assertStatus(404);
        $client->putJson("/api/v1/strategy/signals/{$signalB->id}/dismiss")->assertStatus(404);

        expect($signalB->fresh()->is_read)->toBeFalse();
    });
});

// ─── 8. StrategyAdvisorController ─────────────────────────────────────────────

describe('Chantier 32.27 — StrategyAdvisorController plan/session ownership', function () {
    test('insights()/boardReport() 404 on another company\'s plan id', function () {
        $userA = strategyUser();
        $userB = strategyUser();
        $planB = StrategyPlan::create(['tenant_id' => (string) $userB->company_id, 'name' => 'Board plan B', 'period_start' => 2026, 'period_end' => 2027, 'status' => 'active']);

        $client = test()->actingAs($userA, 'sanctum');

        $client->getJson("/api/v1/strategy/advisor/insights?plan_id={$planB->id}")->assertStatus(404);
        $client->postJson('/api/v1/strategy/advisor/board-report', ['plan_id' => $planB->id])->assertStatus(404);
    });

    test('ritualSummary() 404s on another company\'s ritual session id', function () {
        $userA = strategyUser();
        $userB = strategyUser();
        $ritualB = StrategyRitual::create(['tenant_id' => (string) $userB->company_id, 'name' => 'R', 'type' => 'weekly_checkin', 'cadence' => 'weekly', 'is_active' => true]);
        $sessionB = StrategyRitualSession::create(['ritual_id' => $ritualB->id, 'scheduled_at' => now()]);

        test()->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/strategy/advisor/ritual-summary', ['session_id' => $sessionB->id])
            ->assertStatus(404);
    });
});

// ─── 9. Fake/dead cleanup regression ──────────────────────────────────────────

describe('Chantier 32.27 — fake/dead cleanup', function () {
    test('the dead KRO model/table are confirmed gone', function () {
        expect(class_exists(\Modules\Strategy\Models\KRO::class))->toBeFalse();
        expect(DB::getSchemaBuilder()->hasTable('strategy_kros'))->toBeFalse();
    });

    test('the OkrController show() endpoint exists and returns the real objective', function () {
        $user = strategyUser();
        $plan = StrategyPlan::create(['tenant_id' => (string) $user->company_id, 'name' => 'P', 'period_start' => 2026, 'period_end' => 2027, 'status' => 'active']);
        $obj  = StrategyObjective::create(['plan_id' => $plan->id, 'level' => 'strategic', 'title' => 'Real Objective', 'status' => 'active', 'progress' => 0]);

        test()->actingAs($user, 'sanctum')
            ->getJson("/api/v1/strategy/objectives/{$obj->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Real Objective');
    });
});
