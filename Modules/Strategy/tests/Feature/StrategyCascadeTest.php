<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Services\AlignmentCascadeService;
use App\Models\User;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Create a StrategyPlan owned by the given user.
 */
function createPlan(User $user): StrategyPlan
{
    return StrategyPlan::create([
        'tenant_id'    => $user->tenant_id ?? 'default',
        'name'         => 'Test Plan 2026',
        'vision'       => 'Market leadership',
        'mission'      => 'Deliver excellence',
        'period_start' => 2026,
        'period_end'   => 2027,
        'framework'    => 'OKR',
        'status'       => 'active',
        'health_score' => 80,
        'created_by'   => $user->id,
    ]);
}

/**
 * Create a StrategyObjective with the given overrides.
 *
 * @param  array<string, mixed>  $overrides
 */
function createObjective(StrategyPlan $plan, array $overrides = []): StrategyObjective
{
    return StrategyObjective::create(array_merge([
        'plan_id'  => $plan->id,
        'title'    => 'Default Objective',
        'level'    => 'company',
        'status'   => 'on_track',
        'progress' => 75.0,
    ], $overrides));
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('GET /api/v1/strategy/cascade', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->plan = createPlan($this->user);
    });

    // ── Test 1 ────────────────────────────────────────────────────────────────

    test('cascade endpoint returns 200 with nodes array', function () {
        createObjective($this->plan);

        $response = $this->getJson('/api/v1/strategy/cascade');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'nodes',
                         'stats',
                     ],
                 ]);

        expect($response->json('data.nodes'))->toBeArray();
    });

    // ── Test 2 ────────────────────────────────────────────────────────────────

    test('cascade node has all required fields', function () {
        createObjective($this->plan, [
            'title'    => 'Objectif principal',
            'level'    => 'department',
            'status'   => 'at_risk',
            'progress' => 50.0,
        ]);

        $response = $this->getJson('/api/v1/strategy/cascade');

        $response->assertStatus(200);

        $nodes = $response->json('data.nodes');
        expect($nodes)->toBeArray()->not->toBeEmpty();

        $node = $nodes[0];
        expect($node)->toHaveKey('id')
                     ->toHaveKey('title')
                     ->toHaveKey('level')
                     ->toHaveKey('rag')
                     ->toHaveKey('progress')
                     ->toHaveKey('parent_id')
                     ->toHaveKey('children')
                     ->toHaveKey('linked_ratios');

        expect($node['title'])->toBe('Objectif principal')
            ->and($node['level'])->toBe('department')
            ->and($node['rag'])->toBeIn(['green', 'amber', 'red'])
            ->and($node['children'])->toBeArray()
            ->and($node['linked_ratios'])->toBeArray();
    });

    // ── Test 3 ────────────────────────────────────────────────────────────────

    test('RAG is green for progress >= 70, amber for 40-69, red for < 40', function () {
        createObjective($this->plan, ['title' => 'Green obj',  'status' => 'draft', 'progress' => 80.0]);
        createObjective($this->plan, ['title' => 'Amber obj',  'status' => 'draft', 'progress' => 55.0]);
        createObjective($this->plan, ['title' => 'Red obj',    'status' => 'draft', 'progress' => 20.0]);

        $service = app(AlignmentCascadeService::class);
        $map     = $service->getCascadeMap('default');
        $nodes   = $map['nodes'];

        $findByTitle = fn (string $t) => collect($nodes)->firstWhere('title', $t);

        $green = $findByTitle('Green obj');
        $amber = $findByTitle('Amber obj');
        $red   = $findByTitle('Red obj');

        expect($green['rag'])->toBe('green')
            ->and($amber['rag'])->toBe('amber')
            ->and($red['rag'])->toBe('red');
    });

    // ── Test 4 ────────────────────────────────────────────────────────────────

    test('stats counts total, on_track, at_risk, behind correctly', function () {
        createObjective($this->plan, ['status' => 'on_track', 'progress' => 75.0]);
        createObjective($this->plan, ['status' => 'on_track', 'progress' => 90.0]);
        createObjective($this->plan, ['status' => 'at_risk',  'progress' => 50.0]);
        createObjective($this->plan, ['status' => 'behind',   'progress' => 15.0]);

        $service = app(AlignmentCascadeService::class);
        $map     = $service->getCascadeMap('default');
        $stats   = $map['stats'];

        expect($stats['total'])->toBe(4)
            ->and($stats['on_track'])->toBe(2)
            ->and($stats['at_risk'])->toBe(1)
            ->and($stats['behind'])->toBe(1);
    });

    // ── Test 5 ────────────────────────────────────────────────────────────────

    test('children appear nested under correct parent in the tree', function () {
        $parent = createObjective($this->plan, [
            'title'     => 'Parent Objective',
            'level'     => 'company',
            'parent_id' => null,
            'progress'  => 60.0,
        ]);

        $child1 = createObjective($this->plan, [
            'title'     => 'Child One',
            'level'     => 'department',
            'parent_id' => $parent->id,
            'progress'  => 40.0,
        ]);

        $child2 = createObjective($this->plan, [
            'title'     => 'Child Two',
            'level'     => 'team',
            'parent_id' => $parent->id,
            'progress'  => 80.0,
        ]);

        $service = app(AlignmentCascadeService::class);
        $map     = $service->getCascadeMap('default');

        // Tree roots (parent_id = null)
        $roots = $map['nodes'];
        expect($roots)->not->toBeEmpty();

        $parentNode = collect($roots)->firstWhere('id', $parent->id);
        expect($parentNode)->not->toBeNull();

        $childIds = collect($parentNode['children'])->pluck('id')->toArray();
        expect($childIds)->toContain($child1->id)
                         ->toContain($child2->id);

        // Verify children are NOT in the root level
        $rootIds = collect($roots)->pluck('id')->toArray();
        expect($rootIds)->not->toContain($child1->id)
                             ->not->toContain($child2->id);
    });
});
