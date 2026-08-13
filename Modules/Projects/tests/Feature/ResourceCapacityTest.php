<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ResourceAllocation;
use Modules\Projects\Models\ResourceCapacity;
use Modules\Projects\Services\ResourceCapacityService;


// ---------------------------------------------------------------------------
// Model: ResourceAllocation
// ---------------------------------------------------------------------------

test('ResourceAllocation totalPlannedHours calculates correctly', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'hours_per_day' => 8.0,
        'allocation_percent' => 50,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-11', // 10 days diff
    ]);

    // 8.0 * 10 * 0.5 = 40.0
    expect($allocation->totalPlannedHours())->toBe(40.0);
});

test('ResourceAllocation utilizationPercent calculates correctly', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'hours_per_day' => 8.0,
        'allocation_percent' => 100,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-11', // 10 days
        'actual_hours_logged' => 40.0,
    ]);

    // planned = 8 * 10 * 1.0 = 80, actual = 40, utilization = 50%
    expect($allocation->utilizationPercent())->toBe(50.0);
});

test('ResourceAllocation utilizationPercent returns 0 when no planned hours', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'hours_per_day' => 0.0,
        'allocation_percent' => 100,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-01', // 0 days diff
    ]);

    expect($allocation->utilizationPercent())->toBe(0.0);
});

test('ResourceAllocation isOverlapping detects overlap correctly', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'start_date' => '2026-05-10',
        'end_date' => '2026-05-20',
    ]);

    expect($allocation->isOverlapping(Carbon::parse('2026-05-15'), Carbon::parse('2026-05-25')))->toBeTrue();
    expect($allocation->isOverlapping(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-09')))->toBeFalse();
    expect($allocation->isOverlapping(Carbon::parse('2026-05-21'), Carbon::parse('2026-05-30')))->toBeFalse();
    expect($allocation->isOverlapping(Carbon::parse('2026-05-10'), Carbon::parse('2026-05-20')))->toBeTrue();
});

test('ResourceAllocation isActive returns true for active allocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'status' => 'confirmed',
        'start_date' => Carbon::today()->subDays(5)->toDateString(),
        'end_date' => Carbon::today()->addDays(5)->toDateString(),
    ]);

    expect($allocation->isActive())->toBeTrue();
});

test('ResourceAllocation isActive returns false for cancelled allocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'status' => 'cancelled',
        'start_date' => Carbon::today()->subDays(5)->toDateString(),
        'end_date' => Carbon::today()->addDays(5)->toDateString(),
    ]);

    expect($allocation->isActive())->toBeFalse();
});

test('ResourceAllocation isActive returns false for past allocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'status' => 'planned',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    expect($allocation->isActive())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Model: ResourceCapacity
// ---------------------------------------------------------------------------

test('ResourceCapacity isAvailable returns true when not on leave or holiday', function () {
    $user = actingAsUser('employee');
    $capacity = ResourceCapacity::factory()->create([
        'user_id' => $user->id,
        'is_holiday' => false,
        'is_leave' => false,
        'available_hours' => 8.0,
    ]);

    expect($capacity->isAvailable())->toBeTrue();
});

test('ResourceCapacity isAvailable returns false on holiday', function () {
    $user = actingAsUser('employee');
    $capacity = ResourceCapacity::factory()->create([
        'user_id' => $user->id,
        'is_holiday' => true,
        'is_leave' => false,
    ]);

    expect($capacity->isAvailable())->toBeFalse();
});

test('ResourceCapacity isAvailable returns false on leave', function () {
    $user = actingAsUser('employee');
    $capacity = ResourceCapacity::factory()->create([
        'user_id' => $user->id,
        'is_holiday' => false,
        'is_leave' => true,
    ]);

    expect($capacity->isAvailable())->toBeFalse();
});

test('ResourceCapacity effectiveHours returns 0 on holiday', function () {
    $user = actingAsUser('employee');
    $capacity = ResourceCapacity::factory()->create([
        'user_id' => $user->id,
        'is_holiday' => true,
        'available_hours' => 8.0,
    ]);

    expect($capacity->effectiveHours())->toBe(0.0);
});

test('ResourceCapacity effectiveHours returns 0 on leave', function () {
    $user = actingAsUser('employee');
    $capacity = ResourceCapacity::factory()->create([
        'user_id' => $user->id,
        'is_leave' => true,
        'available_hours' => 8.0,
    ]);

    expect($capacity->effectiveHours())->toBe(0.0);
});

test('ResourceCapacity effectiveHours returns available_hours when working', function () {
    $user = actingAsUser('employee');
    $capacity = ResourceCapacity::factory()->create([
        'user_id' => $user->id,
        'is_holiday' => false,
        'is_leave' => false,
        'available_hours' => 6.0,
    ]);

    expect($capacity->effectiveHours())->toBe(6.0);
});

// ---------------------------------------------------------------------------
// Service: ResourceCapacityService
// ---------------------------------------------------------------------------

test('service allocate creates a ResourceAllocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $service = app(ResourceCapacityService::class);

    $allocation = $service->allocate($user->id, $project->id, [
        'allocation_type' => 'part_time',
        'allocation_percent' => 50,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'hours_per_day' => 4.0,
        'status' => 'planned',
    ]);

    expect($allocation)->toBeInstanceOf(ResourceAllocation::class);
    expect($allocation->user_id)->toBe($user->id);
    expect($allocation->project_id)->toBe($project->id);
});

test('service allocate throws when overallocated', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $service = app(ResourceCapacityService::class);

    // Already at 80%
    $service->allocate($user->id, $project->id, [
        'allocation_percent' => 80,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
    ]);

    // Adding 40% would exceed 100%
    expect(fn () => $service->allocate($user->id, $project->id, [
        'allocation_percent' => 40,
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-30',
    ]))->toThrow(ValidationException::class);
});

test('service checkOverallocation detects overallocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $service = app(ResourceCapacityService::class);

    ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'allocation_percent' => 80,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'status' => 'planned',
    ]);

    $result = $service->checkOverallocation(
        $user->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-10'),
        30 // additional percent
    );

    expect($result['overallocated'])->toBeTrue();
    expect($result['max_allocation'])->toBeGreaterThan(100);
    expect($result['conflicts'])->not->toBeEmpty();
});

test('service checkOverallocation returns not overallocated when under 100%', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $service = app(ResourceCapacityService::class);

    ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'allocation_percent' => 50,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'status' => 'planned',
    ]);

    $result = $service->checkOverallocation(
        $user->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-10'),
        30 // 50 + 30 = 80, still under 100
    );

    expect($result['overallocated'])->toBeFalse();
    expect($result['conflicts'])->toBeEmpty();
});

test('service utilizationReport returns per-user data', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $service = app(ResourceCapacityService::class);

    ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'hours_per_day' => 8.0,
        'allocation_percent' => 100,
        'actual_hours_logged' => 20.0,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
    ]);

    $report = $service->utilizationReport(
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30')
    );

    expect($report)->toBeArray();
    expect(count($report))->toBe(1);
    expect($report[0]['user_id'])->toBe($user->id);
    expect($report[0]['actual_hours'])->toBe(20.0);
    expect($report[0])->toHaveKeys(['name', 'total_planned_hours', 'actual_hours', 'utilization_pct', 'allocations']);
});

test('service getAvailability returns day-by-day availability', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $service = app(ResourceCapacityService::class);

    ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'allocation_percent' => 50,
        'hours_per_day' => 8.0,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
        'status' => 'planned',
    ]);

    $availability = $service->getAvailability(
        $user->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-03')
    );

    // 8.0 * 0.5 = 4.0 allocated, 8.0 - 4.0 = 4.0 available
    expect($availability)->toBeArray();
    expect($availability['2026-06-01'])->toBe(4.0);
    expect($availability['2026-06-02'])->toBe(4.0);
});

test('service getAvailability respects leave records', function () {
    $user = actingAsUser('employee');
    $service = app(ResourceCapacityService::class);

    ResourceCapacity::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-06-02',
        'is_leave' => true,
        'available_hours' => 0.0,
    ]);

    $availability = $service->getAvailability(
        $user->id,
        Carbon::parse('2026-06-02'),
        Carbon::parse('2026-06-02')
    );

    expect($availability['2026-06-02'])->toBe(0.0);
});

test('service setLeave creates ResourceCapacity records', function () {
    $user = actingAsUser('employee');
    $service = app(ResourceCapacityService::class);

    $records = $service->setLeave(
        $user->id,
        Carbon::parse('2026-06-10'),
        Carbon::parse('2026-06-12'),
        false
    );

    expect($records)->toBeArray();
    expect(count($records))->toBe(3); // 3 days

    $dbRecord = ResourceCapacity::where('user_id', $user->id)
        ->whereDate('date', '2026-06-10')
        ->first();

    expect($dbRecord)->not->toBeNull();
    expect($dbRecord->is_leave)->toBeTrue();
    expect($dbRecord->is_holiday)->toBeFalse();
});

test('service setLeave creates holiday records', function () {
    $user = actingAsUser('employee');
    $service = app(ResourceCapacityService::class);

    $service->setLeave(
        $user->id,
        Carbon::parse('2026-12-25'),
        Carbon::parse('2026-12-25'),
        true
    );

    $record = ResourceCapacity::where('user_id', $user->id)
        ->whereDate('date', '2026-12-25')
        ->first();

    expect($record->is_holiday)->toBeTrue();
    expect($record->is_leave)->toBeFalse();
});

test('service getProjectDemand returns project allocations', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $service = app(ResourceCapacityService::class);

    ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
    ]);

    $demand = $service->getProjectDemand(
        $project->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30')
    );

    expect($demand)->toBeArray();
    expect(count($demand))->toBe(1);
    expect($demand[0]['project_id'])->toBe($project->id);
    expect($demand[0])->toHaveKeys(['id', 'user_id', 'user_name', 'allocation_percent', 'start_date', 'end_date', 'total_planned_hours']);
});

test('service suggestAllocations returns candidates', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $service = app(ResourceCapacityService::class);

    $suggestions = $service->suggestAllocations($project->id, [
        [
            'hours' => 40,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ],
    ]);

    expect($suggestions)->toBeArray();
    expect(count($suggestions))->toBe(1);
    expect($suggestions[0])->toHaveKey('requirement');
    expect($suggestions[0])->toHaveKey('candidates');
    expect($suggestions[0]['candidates'])->toBeArray();
});

// ---------------------------------------------------------------------------
// API: Allocations CRUD
// ---------------------------------------------------------------------------

test('authenticated user can list allocations', function () {
    $user = actingAsUser('employee');

    $this
        ->getJson('/api/v1/allocations')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('unauthenticated user cannot list allocations', function () {
    $this->getJson('/api/v1/allocations')
        ->assertUnauthorized();
});

test('authenticated user can create an allocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $this
        ->postJson('/api/v1/allocations', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'allocation_type' => 'part_time',
            'allocation_percent' => 50,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'hours_per_day' => 4.0,
        ])
        ->assertCreated()
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('project_id', $project->id);
});

test('store allocation returns 422 when overallocated', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    // First allocation at 80%
    $this
        ->postJson('/api/v1/allocations', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'allocation_percent' => 80,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ])
        ->assertCreated();

    // Second at 40% — should conflict
    $this
        ->postJson('/api/v1/allocations', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'allocation_percent' => 40,
            'start_date' => '2026-07-15',
            'end_date' => '2026-07-31',
        ])
        ->assertUnprocessable();
});

test('authenticated user can show an allocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    $this
        ->getJson("/api/v1/allocations/{$allocation->id}")
        ->assertOk()
        ->assertJsonPath('id', $allocation->id);
});

test('authenticated user can update an allocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'status' => 'planned',
    ]);

    $this
        ->putJson("/api/v1/allocations/{$allocation->id}", [
            'status' => 'confirmed',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');
});

test('authenticated user can delete an allocation', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $allocation = ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    $this
        ->deleteJson("/api/v1/allocations/{$allocation->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('prj_resource_allocations', ['id' => $allocation->id]);
});

// ---------------------------------------------------------------------------
// API: Capacity endpoints
// ---------------------------------------------------------------------------

test('utilization report endpoint returns data', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
    ]);

    $this
        ->getJson('/api/v1/capacity/utilization?from=2026-06-01&to=2026-06-30')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('check overallocation endpoint returns result', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'allocation_percent' => 80,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'status' => 'planned',
    ]);

    $this
        ->getJson("/api/v1/capacity/check-overallocation?user_id={$user->id}&from=2026-06-01&to=2026-06-30")
        ->assertOk()
        ->assertJsonStructure(['overallocated', 'max_allocation', 'conflicts']);
});

test('get availability endpoint returns day map', function () {
    $user = actingAsUser('employee');

    $this
        ->getJson("/api/v1/capacity/users/{$user->id}/availability?from=2026-06-01&to=2026-06-03")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('set leave endpoint creates capacity records', function () {
    $user = actingAsUser('employee');

    $this
        ->postJson("/api/v1/capacity/users/{$user->id}/leave", [
            'from' => '2026-06-20',
            'to' => '2026-06-21',
            'is_holiday' => false,
        ])
        ->assertStatus(201)
        ->assertJsonStructure(['data']);

    $record = ResourceCapacity::where('user_id', $user->id)
        ->whereDate('date', '2026-06-20')
        ->first();
    expect($record)->not->toBeNull();
    expect($record->is_leave)->toBeTrue();
});

test('suggest allocations endpoint returns candidates', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $this
        ->postJson('/api/v1/capacity/suggest', [
            'project_id' => $project->id,
            'requirements' => [
                [
                    'hours' => 40,
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-07-31',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('get project demand endpoint returns allocations', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    ResourceAllocation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
    ]);

    $this
        ->getJson("/api/v1/projects/{$project->id}/demand?from=2026-07-01&to=2026-07-31")
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('allocation index can filter by user_id', function () {
    $user1 = actingAsUser('employee');
    $user2 = User::factory()->create();
    $project = Project::factory()->create();

    ResourceAllocation::factory()->create(['user_id' => $user1->id, 'project_id' => $project->id]);
    ResourceAllocation::factory()->create(['user_id' => $user2->id, 'project_id' => $project->id]);

    $response = $this
        ->getJson("/api/v1/allocations?user_id={$user1->id}")
        ->assertOk();

    $data = $response->json('data');
    expect(collect($data)->pluck('user_id')->unique()->values()->all())->toBe([$user1->id]);
});

test('allocation index can filter by project_id', function () {
    $user = actingAsUser('employee');
    $project1 = Project::factory()->create();
    $project2 = Project::factory()->create();

    ResourceAllocation::factory()->create(['user_id' => $user->id, 'project_id' => $project1->id]);
    ResourceAllocation::factory()->create(['user_id' => $user->id, 'project_id' => $project2->id]);

    $response = $this
        ->getJson("/api/v1/allocations?project_id={$project1->id}")
        ->assertOk();

    $data = $response->json('data');
    expect(collect($data)->pluck('project_id')->unique()->values()->all())->toBe([$project1->id]);
});
