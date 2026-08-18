<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimesheetPeriod;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimeTrackingProject;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// TimesheetPeriod model
//
// Chantier 8.4: Modules\Timesheets\Models\Timesheet (tested here previously)
// was deleted — a broken duplicate whose $fillable never matched its own
// timesheets_sheets stub table. TimesheetPeriod is the real model serving
// this same "weekly submission" role, backed by a real, migrated table.
// ─────────────────────────────────────────────────────────────────────────────

test('TimesheetPeriod model uses correct table name', function () {
    expect((new TimesheetPeriod())->getTable())->toBe('ts_timesheet_periods');
});

test('TimesheetPeriod model has correct fillable fields', function () {
    $model = new TimesheetPeriod();

    expect($model->getFillable())->toContain('employee_id')
        ->toContain('period_start')
        ->toContain('period_end')
        ->toContain('status')
        ->toContain('total_hours')
        ->toContain('billable_hours');
});

test('TimesheetPeriod model casts period_start and period_end as dates', function () {
    $casts = (new TimesheetPeriod())->getCasts();

    expect($casts['period_start'])->toBe('date')
        ->and($casts['period_end'])->toBe('date');
});

test('TimesheetPeriod model casts total_hours and billable_hours as decimal', function () {
    $casts = (new TimesheetPeriod())->getCasts();

    expect($casts['total_hours'])->toContain('decimal')
        ->and($casts['billable_hours'])->toContain('decimal');
});

test('TimesheetPeriod model has employee belongs-to relation', function () {
    expect((new TimesheetPeriod())->employee())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('TimesheetPeriod model has submitter belongs-to relation', function () {
    expect((new TimesheetPeriod())->submitter())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('TimesheetPeriod model has approver belongs-to relation', function () {
    expect((new TimesheetPeriod())->approver())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('TimesheetPeriod canBeSubmitted is true only when status is draft', function () {
    expect((new TimesheetPeriod(['status' => 'draft']))->canBeSubmitted())->toBeTrue()
        ->and((new TimesheetPeriod(['status' => 'submitted']))->canBeSubmitted())->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// TimesheetEntry model
// ─────────────────────────────────────────────────────────────────────────────

test('TimesheetEntry model uses correct table name', function () {
    expect((new TimesheetEntry())->getTable())->toBe('timesheet_entries');
});

test('TimesheetEntry model has correct fillable fields', function () {
    $model = new TimesheetEntry();

    expect($model->getFillable())->toContain('employee_id')
        ->toContain('entry_date')
        ->toContain('hours_worked')
        ->toContain('status')
        ->toContain('project_id')
        ->toContain('description');
});

test('TimesheetEntry model uses SoftDeletes', function () {
    expect(in_array(
        \Illuminate\Database\Eloquent\SoftDeletes::class,
        class_uses_recursive(TimesheetEntry::class)
    ))->toBeTrue();
});

test('TimesheetEntry isApproved returns true when status is approved', function () {
    $entry = new TimesheetEntry(['status' => 'approved']);

    expect($entry->isApproved())->toBeTrue();
});

test('TimesheetEntry isApproved returns false when status is draft', function () {
    $entry = new TimesheetEntry(['status' => 'draft']);

    expect($entry->isApproved())->toBeFalse();
});

test('TimesheetEntry canEdit returns true when status is draft', function () {
    $entry = new TimesheetEntry(['status' => 'draft']);

    expect($entry->canEdit())->toBeTrue();
});

test('TimesheetEntry canEdit returns false when status is submitted', function () {
    $entry = new TimesheetEntry(['status' => 'submitted']);

    expect($entry->canEdit())->toBeFalse();
});

test('TimesheetEntry isSubmitted returns true for submitted status', function () {
    $entry = new TimesheetEntry(['status' => 'submitted']);

    expect($entry->isSubmitted())->toBeTrue();
});

test('TimesheetEntry isSubmitted returns true for approved status', function () {
    $entry = new TimesheetEntry(['status' => 'approved']);

    expect($entry->isSubmitted())->toBeTrue();
});

test('TimesheetEntry has allocations has-many relation', function () {
    expect((new TimesheetEntry())->allocations())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('TimesheetEntry has employee belongs-to relation', function () {
    expect((new TimesheetEntry())->employee())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('TimesheetEntry has project belongs-to relation', function () {
    expect((new TimesheetEntry())->project())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// module.json
// ─────────────────────────────────────────────────────────────────────────────

test('Timesheets module.json has correct name and version', function () {
    $jsonPath = base_path('Modules/Timesheets/module.json');

    if (!file_exists($jsonPath)) {
        $this->markTestSkipped('module.json not found.');
    }

    $json = json_decode(file_get_contents($jsonPath), true);

    expect($json['name'])->toBe('Timesheets')
        ->and($json)->toHaveKey('version');
});
