<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimeTrackingProject;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────────
// TimesheetEntry MODEL TESTS
// ──────────────────────────────────────────────────────────────────

it('creates a timesheet entry with required fields', function () {
    $user = User::factory()->create();

    $entry = TimesheetEntry::factory()->create([
        'employee_id' => $user->id,
        'hours_worked' => 8.0,
        'status'       => 'draft',
    ]);

    expect($entry->id)->toBeInt()
        ->and($entry->status)->toBe('draft')
        ->and($entry->hours_worked)->toBe(8.0);
});

it('entry in draft status can be edited', function () {
    $entry = TimesheetEntry::factory()->create(['status' => 'draft']);

    expect($entry->canEdit())->toBeTrue();
});

it('entry in submitted status cannot be edited', function () {
    $entry = TimesheetEntry::factory()->create(['status' => 'submitted']);

    expect($entry->canEdit())->toBeFalse();
});

it('entry in approved status cannot be edited', function () {
    $entry = TimesheetEntry::factory()->create(['status' => 'approved']);

    expect($entry->canEdit())->toBeFalse();
});

it('isApproved returns true only when status is approved', function () {
    $approved  = TimesheetEntry::factory()->create(['status' => 'approved']);
    $submitted = TimesheetEntry::factory()->create(['status' => 'submitted']);

    expect($approved->isApproved())->toBeTrue()
        ->and($submitted->isApproved())->toBeFalse();
});

it('isSubmitted returns true for submitted and approved entries', function () {
    $submitted = TimesheetEntry::factory()->create(['status' => 'submitted']);
    $approved  = TimesheetEntry::factory()->create(['status' => 'approved']);
    $draft     = TimesheetEntry::factory()->create(['status' => 'draft']);

    expect($submitted->isSubmitted())->toBeTrue()
        ->and($approved->isSubmitted())->toBeTrue()
        ->and($draft->isSubmitted())->toBeFalse();
});

it('scopeSubmitted returns only submitted entries', function () {
    TimesheetEntry::factory()->count(3)->create(['status' => 'submitted']);
    TimesheetEntry::factory()->count(2)->create(['status' => 'draft']);

    $results = TimesheetEntry::submitted()->get();

    expect($results)->toHaveCount(3);
});

it('scopeApproved returns only approved entries', function () {
    TimesheetEntry::factory()->count(2)->create(['status' => 'approved']);
    TimesheetEntry::factory()->count(4)->create(['status' => 'draft']);

    $results = TimesheetEntry::approved()->get();

    expect($results)->toHaveCount(2);
});

it('scopeByEmployee filters by employee id', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    TimesheetEntry::factory()->count(3)->create(['employee_id' => $user1->id]);
    TimesheetEntry::factory()->count(2)->create(['employee_id' => $user2->id]);

    $results = TimesheetEntry::byEmployee($user1->id)->get();

    expect($results)->toHaveCount(3);
});

it('scopeByDateRange filters entries within range', function () {
    $user = User::factory()->create();

    TimesheetEntry::factory()->create([
        'employee_id' => $user->id,
        'entry_date'  => now()->subDays(5)->toDateString(),
    ]);
    TimesheetEntry::factory()->create([
        'employee_id' => $user->id,
        'entry_date'  => now()->subDays(20)->toDateString(),
    ]);

    $results = TimesheetEntry::byDateRange(
        now()->subDays(10)->toDateString(),
        now()->toDateString()
    )->get();

    expect($results)->toHaveCount(1);
});

// ──────────────────────────────────────────────────────────────────
// TimeTrackingProject MODEL TESTS
// ──────────────────────────────────────────────────────────────────

it('creates a time tracking project with active status', function () {
    $project = TimeTrackingProject::factory()->create([
        'status'       => 'active',
        'budget_hours' => 200,
        'hours_tracked' => 0,
    ]);

    expect($project->isActive())->toBeTrue()
        ->and($project->budget_hours)->toBe(200.0);
});

it('remaining hours is null when budget hours is not set', function () {
    $project = TimeTrackingProject::factory()->create([
        'budget_hours'  => null,
        'hours_tracked' => 0,
    ]);

    expect($project->remaining_hours)->toBeNull();
});

it('remaining hours calculates correctly when within budget', function () {
    $project = TimeTrackingProject::factory()->create([
        'budget_hours'  => 100,
        'hours_tracked' => 40,
    ]);

    expect($project->remaining_hours)->toBe(60.0);
});

it('is_over_budget returns true when hours tracked exceeds budget', function () {
    $project = TimeTrackingProject::factory()->create([
        'budget_hours'  => 100,
        'hours_tracked' => 120,
    ]);

    expect($project->is_over_budget)->toBeTrue()
        ->and($project->isOverBudget())->toBeTrue();
});

it('remaining hours returns 0 when project is over budget', function () {
    $project = TimeTrackingProject::factory()->create([
        'budget_hours'  => 100,
        'hours_tracked' => 110,
    ]);

    expect($project->remaining_hours)->toBe(0.0);
});

it('scopeActive returns only active projects', function () {
    TimeTrackingProject::factory()->count(3)->create(['status' => 'active']);
    TimeTrackingProject::factory()->count(2)->create(['status' => 'inactive']);

    $active = TimeTrackingProject::active()->get();

    expect($active)->toHaveCount(3);
});

// ──────────────────────────────────────────────────────────────────
// TimeAllocation MODEL TESTS
// ──────────────────────────────────────────────────────────────────

it('time allocation calculateCost returns zero when no hourly rate', function () {
    $allocation = TimeAllocation::factory()->create([
        'hourly_rate'    => null,
        'hours_allocated' => 8,
    ]);

    expect($allocation->calculateCost())->toBe(0.0);
});

it('time allocation calculateCost multiplies hours by rate', function () {
    $allocation = TimeAllocation::factory()->create([
        'hourly_rate'    => 50.0,
        'hours_allocated' => 4,
    ]);

    expect($allocation->calculateCost())->toBe(200.0);
});

it('isBillable returns true when billable field is yes', function () {
    $allocation = TimeAllocation::factory()->create(['billable' => 'yes']);

    expect($allocation->isBillable())->toBeTrue();
});

it('isBillable returns false when billable field is no', function () {
    $allocation = TimeAllocation::factory()->create(['billable' => 'no']);

    expect($allocation->isBillable())->toBeFalse();
});

it('billable scope returns only billable allocations', function () {
    TimeAllocation::factory()->count(3)->create(['billable' => 'yes']);
    TimeAllocation::factory()->count(2)->create(['billable' => 'no']);

    $billable = TimeAllocation::billable()->get();

    expect($billable)->toHaveCount(3);
});

// Chantier 8.4: Modules\Timesheets\Models\TimeEntry (and its TimeEntryController)
// were a fully-unrouted, broken parallel duplicate of the real
// TimesheetEntry/TimesheetEntryController — deleted; its 2 isolated unit
// tests here went with it.
