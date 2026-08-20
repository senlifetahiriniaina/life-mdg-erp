<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPlan;

/**
 * Chantier 19 (Lot 5): re-verified Strategy's tenant-scoping empirically
 * rather than trusting CLAUDE.md's own earlier "already fixed" claims — this
 * exact module has a documented history of that failure mode (Chantier 8.6
 * "fixed" a real leak by replacing a client-controlled header with the
 * equally-phantom users.tenant_id column, undetected for a full remediation
 * pass until Chantier 10 caught it). StrategyPlanController/KpiController/
 * RatioController/etc.'s own `tenantId()` helpers were confirmed still
 * correct (company_id, no client-controlled fallback) — but
 * OkrController/StrategyObjectiveLinkController had never been re-verified
 * at all: StrategyObjective has no tenant_id column of its own (only
 * inherited via its plan), and neither controller had ever checked it.
 * Found and fixed 3 real, confirmed cross-tenant bugs:
 *   1. OkrController::index() listed every company's objectives whenever
 *      plan_id was omitted (the common case for the real Objectives page).
 *   2. OkrController::update()/destroy()/cascade()/storeKr()/updateKr()/
 *      updateProgress() let any user of any company mutate/delete another
 *      company's objective/key-result by id — StrategyObjectivePolicy::
 *      canManage() only checks role, never per-record ownership.
 *   3. StrategyObjectiveLinkController's link/unlink/bulkLink/
 *      updateContribution/getLinkedResources/getAggregatedContribution/
 *      getResourceHierarchy had zero tenant ownership check of any kind.
 */
function tenantCoUser(string $name): array
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $company = Company::factory()->create(['name' => $name]);
    $user = User::factory()->create(['company_id' => $company->id]);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole('admin');

    return [$company, $user];
}

function tenantPlanAndObjective(int $companyId): array
{
    $plan = StrategyPlan::create(['tenant_id' => (string) $companyId, 'name' => 'Plan ' . uniqid(), 'status' => 'active']);
    $objective = StrategyObjective::create([
        'plan_id' => $plan->id, 'title' => 'Objective ' . uniqid(), 'level' => 'annual', 'status' => 'active',
    ]);

    return [$plan, $objective];
}

test('OkrController::index only lists the caller\'s own company objectives', function () {
    [$coA, $userA] = tenantCoUser('A');
    [$coB, $userB] = tenantCoUser('B');
    [, $objA] = tenantPlanAndObjective($coA->id);
    [, $objB] = tenantPlanAndObjective($coB->id);

    $resp = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/strategy/objectives');
    $resp->assertOk();

    $titles = collect($resp->json('data'))->pluck('title');
    expect($titles)->not->toContain($objA->title);
    expect($titles)->toContain($objB->title);
});

test('OkrController::update/destroy reject another company\'s objective with 404', function () {
    [$coA, $userA] = tenantCoUser('A');
    [, $userB] = tenantCoUser('B');
    [, $objA] = tenantPlanAndObjective($coA->id);

    $this->actingAs($userB, 'sanctum')
        ->putJson("/api/v1/strategy/objectives/{$objA->id}", ['title' => 'Hacked'])
        ->assertNotFound();

    expect($objA->fresh()->title)->not->toBe('Hacked');

    $this->actingAs($userB, 'sanctum')
        ->deleteJson("/api/v1/strategy/objectives/{$objA->id}")
        ->assertNotFound();

    expect(StrategyObjective::find($objA->id))->not->toBeNull();

    // The real owner can still manage their own objective.
    $this->actingAs($userA, 'sanctum')
        ->putJson("/api/v1/strategy/objectives/{$objA->id}", ['title' => 'Renamed by owner'])
        ->assertOk();
    expect($objA->fresh()->title)->toBe('Renamed by owner');
});

test('OkrController::storeKr/updateKr/updateProgress reject another company\'s objective/key result', function () {
    [$coA, $userA] = tenantCoUser('A');
    [, $userB] = tenantCoUser('B');
    [, $objA] = tenantPlanAndObjective($coA->id);

    $this->actingAs($userB, 'sanctum')->postJson('/api/v1/strategy/key-results', [
        'objective_id' => $objA->id,
        'title'        => 'KR by B',
        'target_value' => 100,
    ])->assertNotFound();

    $kr = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/strategy/key-results', [
        'objective_id' => $objA->id,
        'title'        => 'KR by A',
        'target_value' => 100,
    ])->assertCreated()->json();

    $this->actingAs($userB, 'sanctum')
        ->putJson("/api/v1/strategy/key-results/{$kr['id']}", ['title' => 'Hacked KR'])
        ->assertNotFound();

    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/strategy/key-results/{$kr['id']}/progress", ['current_value' => 999])
        ->assertNotFound();
});

test('StrategyObjectiveLinkController rejects link/unlink/read on another company\'s objective', function () {
    [$coA, $userA] = tenantCoUser('A');
    [, $userB] = tenantCoUser('B');
    [, $objA] = tenantPlanAndObjective($coA->id);

    // link() 404s against a foreign objective_id
    $this->actingAs($userB, 'sanctum')->postJson('/api/v1/strategy/objective-links/link', [
        'objective_id'  => $objA->id,
        'linkable_type' => 'CRM/Opportunity',
        'linkable_id'   => 999,
    ])->assertNotFound();

    // the real owner links a resource for real
    $link = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/strategy/objective-links/link', [
        'objective_id'  => $objA->id,
        'linkable_type' => 'CRM/Opportunity',
        'linkable_id'   => 999,
        'contribution_value' => 500,
    ])->assertCreated()->json('link');

    // getLinkedResources/getAggregatedContribution 404 for the other company
    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/strategy/objective/{$objA->id}/links")
        ->assertNotFound();
    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/strategy/objective/{$objA->id}/aggregated")
        ->assertNotFound();

    // updateContribution/unlinkById 404 against the foreign link id
    $this->actingAs($userB, 'sanctum')
        ->putJson("/api/v1/strategy/objective-links/{$link['id']}", ['contribution_value' => 1])
        ->assertNotFound();
    $this->actingAs($userB, 'sanctum')
        ->deleteJson("/api/v1/strategy/objective-links/{$link['id']}")
        ->assertNotFound();

    // the link must still exist — userB's delete attempt was rejected, not applied
    $this->assertDatabaseHas('strategy_objective_links', ['id' => $link['id']]);

    // unlink-by-resource must not delete another company's link either
    $this->actingAs($userB, 'sanctum')
        ->deleteJson('/api/v1/strategy/resource/CRM/Opportunity/999')
        ->assertOk(); // the endpoint itself always 200s (no-op if nothing matched)
    $this->assertDatabaseHas('strategy_objective_links', ['id' => $link['id']]);

    // getResourceHierarchy must not leak company A's objective title to company B
    $resp = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/strategy/resource/CRM/Opportunity/999');
    $resp->assertOk();
    expect($resp->json('linked'))->toBeFalse();
    expect($resp->json('hierarchy.objective'))->toBeNull();

    // but company A itself still sees it correctly
    $respA = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/strategy/resource/CRM/Opportunity/999');
    $respA->assertOk();
    expect($respA->json('linked'))->toBeTrue();
});
