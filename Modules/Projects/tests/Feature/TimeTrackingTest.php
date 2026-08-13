<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectBilling;
use Modules\Projects\Models\TimeEntry;
use Modules\Projects\Services\TimeTrackingService;


// ---------------------------------------------------------------------------
// Unauthenticated access
// ---------------------------------------------------------------------------

test('unauthenticated user cannot access time entries', function () {
    $this->getJson('/api/v1/projects/time-entries')
        ->assertUnauthorized();
});

test('unauthenticated user cannot start a timer', function () {
    $this->postJson('/api/v1/projects/time-entries/start', [])
        ->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Model: TimeEntry
// ---------------------------------------------------------------------------

describe('TimeEntry model', function () {
    beforeEach(function () {
        $this->user = actingAsUser('employee');
        $this->project = Project::factory()->create();
    });

    test('isBillable returns true when billable is true', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
        ]);
        expect($entry->isBillable())->toBeTrue();
    });

    test('isBillable returns false when billable is false', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => false,
        ]);
        expect($entry->isBillable())->toBeFalse();
    });

    test('isBilled returns true when billed is true', function () {
        $entry = TimeEntry::factory()->billed()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);
        expect($entry->isBilled())->toBeTrue();
    });

    test('isBilled returns false when billed is false', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billed' => false,
        ]);
        expect($entry->isBilled())->toBeFalse();
    });

    test('isRunning returns true when ended_at is null', function () {
        $entry = TimeEntry::factory()->running()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);
        expect($entry->isRunning())->toBeTrue();
    });

    test('isRunning returns false when ended_at is set', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);
        expect($entry->isRunning())->toBeFalse();
    });

    test('stop sets ended_at and computes duration_minutes', function () {
        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $entry = TimeEntry::factory()->running()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'started_at' => Carbon::parse('2026-05-07 10:00:00'),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-07 11:30:00'));
        $entry->stop();

        expect($entry->ended_at)->not->toBeNull();
        expect($entry->duration_minutes)->toEqual(90);
        Carbon::setTestNow();
    });

    test('stop computes duration correctly for partial minutes', function () {
        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $entry = TimeEntry::factory()->running()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'started_at' => Carbon::parse('2026-05-07 10:00:00'),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:30'));
        $entry->stop();

        // 30 seconds → ceil(30/60) = 1 minute
        expect($entry->duration_minutes)->toEqual(1);
        Carbon::setTestNow();
    });

    test('durationInHours returns duration in hours', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'duration_minutes' => 90,
        ]);
        expect($entry->durationInHours())->toEqual(1.5);
    });

    test('durationInHours returns zero when duration_minutes is null', function () {
        $entry = TimeEntry::factory()->running()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);
        expect($entry->durationInHours())->toEqual(0.0);
    });

    test('billableAmount returns correct amount for billable entry', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'duration_minutes' => 120,
            'hourly_rate' => 100.00,
        ]);
        expect($entry->billableAmount())->toEqual(200.0);
    });

    test('billableAmount returns 0 for non-billable entry', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => false,
            'duration_minutes' => 120,
            'hourly_rate' => 100.00,
        ]);
        expect($entry->billableAmount())->toEqual(0.0);
    });

    test('markBilled sets billed to true', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billed' => false,
        ]);
        $entry->markBilled();
        expect($entry->fresh()->isBilled())->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Model: ProjectBilling
// ---------------------------------------------------------------------------

describe('ProjectBilling model', function () {
    beforeEach(function () {
        $this->project = Project::factory()->create();
    });

    test('isActive returns true when status is active', function () {
        $billing = ProjectBilling::factory()->create(['project_id' => $this->project->id, 'status' => 'active']);
        expect($billing->isActive())->toBeTrue();
    });

    test('isActive returns false when status is paused', function () {
        $billing = ProjectBilling::factory()->paused()->create(['project_id' => $this->project->id]);
        expect($billing->isActive())->toBeFalse();
    });

    test('isHourly returns true when billing_type is hourly', function () {
        $billing = ProjectBilling::factory()->create(['project_id' => $this->project->id, 'billing_type' => 'hourly']);
        expect($billing->isHourly())->toBeTrue();
    });

    test('isHourly returns false when billing_type is fixed', function () {
        $billing = ProjectBilling::factory()->fixed()->create(['project_id' => $this->project->id]);
        expect($billing->isHourly())->toBeFalse();
    });

    test('isFixed returns true when billing_type is fixed', function () {
        $billing = ProjectBilling::factory()->fixed()->create(['project_id' => $this->project->id]);
        expect($billing->isFixed())->toBeTrue();
    });

    test('isFixed returns false when billing_type is hourly', function () {
        $billing = ProjectBilling::factory()->create(['project_id' => $this->project->id, 'billing_type' => 'hourly']);
        expect($billing->isFixed())->toBeFalse();
    });

    test('remainingBudgetHours calculates correctly', function () {
        $billing = ProjectBilling::factory()->create([
            'project_id' => $this->project->id,
            'budget_hours' => 100.0,
            'total_hours' => 30.0,
        ]);
        expect($billing->remainingBudgetHours())->toEqual(70.0);
    });

    test('remainingBudgetHours returns budget_hours when total_hours is zero', function () {
        $billing = ProjectBilling::factory()->create([
            'project_id' => $this->project->id,
            'budget_hours' => 50.0,
            'total_hours' => 0,
        ]);
        expect($billing->remainingBudgetHours())->toEqual(50.0);
    });

    test('remainingBudgetAmount calculates correctly', function () {
        $billing = ProjectBilling::factory()->create([
            'project_id' => $this->project->id,
            'budget_amount' => 5000.0,
            'total_billed' => 1500.0,
        ]);
        expect($billing->remainingBudgetAmount())->toEqual(3500.0);
    });

    test('utilizationRate calculates correctly', function () {
        $billing = ProjectBilling::factory()->create([
            'project_id' => $this->project->id,
            'budget_hours' => 100.0,
            'total_hours' => 75.0,
        ]);
        expect($billing->utilizationRate())->toEqual(75.0);
    });

    test('utilizationRate returns 0 when budget_hours is null', function () {
        $billing = ProjectBilling::factory()->create([
            'project_id' => $this->project->id,
            'budget_hours' => null,
            'total_hours' => 10.0,
        ]);
        expect($billing->utilizationRate())->toEqual(0.0);
    });

    test('addBilledAmount increments total_billed', function () {
        $billing = ProjectBilling::factory()->create([
            'project_id' => $this->project->id,
            'total_billed' => 100.0,
        ]);
        $billing->addBilledAmount(250.0);
        expect((float) $billing->fresh()->total_billed)->toEqual(350.0);
    });

    test('addHours increments total_hours', function () {
        $billing = ProjectBilling::factory()->create([
            'project_id' => $this->project->id,
            'total_hours' => 10.0,
        ]);
        $billing->addHours(5.5);
        expect((float) $billing->fresh()->total_hours)->toEqual(15.5);
    });
});

// ---------------------------------------------------------------------------
// Service: TimeTrackingService
// ---------------------------------------------------------------------------

describe('TimeTrackingService', function () {
    beforeEach(function () {
        $this->user = actingAsUser('employee');
        $this->project = Project::factory()->create();
        $this->service = app(TimeTrackingService::class);
    });

    test('startTimer creates a running time entry', function () {
        $entry = $this->service->startTimer($this->project->id, $this->user->id);

        expect($entry)->toBeInstanceOf(TimeEntry::class);
        expect($entry->project_id)->toEqual($this->project->id);
        expect($entry->user_id)->toEqual($this->user->id);
        expect($entry->ended_at)->toBeNull();
    });

    test('stopTimer stops a running entry and computes duration', function () {
        Carbon::setTestNow(Carbon::parse('2026-05-07 08:00:00'));
        $entry = $this->service->startTimer($this->project->id, $this->user->id);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $stopped = $this->service->stopTimer($entry);

        expect($stopped->ended_at)->not->toBeNull();
        expect($stopped->duration_minutes)->toEqual(120);
        Carbon::setTestNow();
    });

    test('logTime creates a completed time entry with computed duration', function () {
        $entry = $this->service->logTime($this->project->id, $this->user->id, [
            'started_at' => '2026-05-07 09:00:00',
            'ended_at' => '2026-05-07 11:30:00',
            'billable' => true,
            'hourly_rate' => 100.0,
        ]);

        expect($entry)->toBeInstanceOf(TimeEntry::class);
        expect($entry->duration_minutes)->toEqual(150);
    });

    test('getProjectTimeEntries returns entries for the project', function () {
        TimeEntry::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);
        $other = Project::factory()->create();
        TimeEntry::factory()->create(['project_id' => $other->id, 'user_id' => $this->user->id]);

        $entries = $this->service->getProjectTimeEntries($this->project->id);
        expect($entries)->toHaveCount(3);
    });

    test('getUserTimeEntries returns entries for the user', function () {
        TimeEntry::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);
        $other = User::factory()->create();
        TimeEntry::factory()->create(['project_id' => $this->project->id, 'user_id' => $other->id]);

        $entries = $this->service->getUserTimeEntries($this->user->id);
        expect($entries)->toHaveCount(2);
    });

    test('getProjectBillableHours sums billable unbilled hours', function () {
        TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'billed' => false,
            'duration_minutes' => 120, // 2h
        ]);
        TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'billed' => false,
            'duration_minutes' => 60, // 1h
        ]);
        TimeEntry::factory()->billed()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'duration_minutes' => 60, // should be excluded (already billed)
        ]);

        $hours = $this->service->getProjectBillableHours($this->project->id);
        expect($hours)->toEqual(3.0);
    });

    test('getBillableAmount sums billable amount for unbilled entries', function () {
        TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'billed' => false,
            'duration_minutes' => 60, // 1h
            'hourly_rate' => 100.00,
        ]);
        TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'billed' => false,
            'duration_minutes' => 120, // 2h
            'hourly_rate' => 50.00,
        ]);

        $amount = $this->service->getBillableAmount($this->project->id);
        expect($amount)->toEqual(200.0); // 100 + 100
    });

    test('markEntriesAsBilled marks all billable unbilled entries', function () {
        TimeEntry::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'billed' => false,
        ]);
        TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => false,
            'billed' => false,
        ]);

        $count = $this->service->markEntriesAsBilled($this->project->id);
        expect($count)->toEqual(3);

        $unbilled = TimeEntry::where('project_id', $this->project->id)
            ->where('billable', true)
            ->where('billed', false)
            ->count();
        expect($unbilled)->toEqual(0);
    });

    test('setupProjectBilling creates billing config', function () {
        $billing = $this->service->setupProjectBilling($this->project->id, [
            'billing_type' => 'hourly',
            'hourly_rate' => 150.0,
            'budget_hours' => 200.0,
        ]);

        expect($billing)->toBeInstanceOf(ProjectBilling::class);
        expect($billing->project_id)->toEqual($this->project->id);
        expect((float) $billing->hourly_rate)->toEqual(150.0);
    });

    test('setupProjectBilling updates existing billing config', function () {
        ProjectBilling::factory()->create([
            'project_id' => $this->project->id,
            'billing_type' => 'fixed',
            'hourly_rate' => 0.0,
        ]);

        $billing = $this->service->setupProjectBilling($this->project->id, [
            'billing_type' => 'hourly',
            'hourly_rate' => 200.0,
        ]);

        expect($billing->billing_type)->toEqual('hourly');
        expect((float) $billing->hourly_rate)->toEqual(200.0);
        expect(ProjectBilling::where('project_id', $this->project->id)->count())->toEqual(1);
    });

    test('getProjectBillingStats returns correct stats', function () {
        TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'billed' => false,
            'duration_minutes' => 60,
            'hourly_rate' => 100.0,
        ]);
        TimeEntry::factory()->billed()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'duration_minutes' => 60,
            'hourly_rate' => 100.0,
        ]);
        TimeEntry::factory()->nonBillable()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'duration_minutes' => 30,
            'hourly_rate' => 100.0,
        ]);

        $stats = $this->service->getProjectBillingStats($this->project->id);

        expect($stats['total_hours'])->toEqual(2.5);
        expect($stats['billable_hours'])->toEqual(2.0);
        expect($stats['billable_amount'])->toEqual(200.0);
        expect($stats['billed_amount'])->toEqual(100.0);
        expect($stats['unbilled_amount'])->toEqual(100.0);
    });
});

// ---------------------------------------------------------------------------
// API: Time Entries
// ---------------------------------------------------------------------------

describe('Time Entries API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('employee');
        $this->project = Project::factory()->create();
    });

    test('can list time entries', function () {
        TimeEntry::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);

        $this->getJson('/api/v1/projects/time-entries')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    });

    test('can filter time entries by project_id', function () {
        TimeEntry::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);
        $other = Project::factory()->create();
        TimeEntry::factory()->create(['project_id' => $other->id, 'user_id' => $this->user->id]);

        $this->getJson("/api/v1/projects/time-entries?project_id={$this->project->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('can log a time entry', function () {
        $this->postJson('/api/v1/projects/time-entries', [
            'project_id' => $this->project->id,
            'started_at' => '2026-05-07 09:00:00',
            'ended_at' => '2026-05-07 11:00:00',
            'description' => 'Worked on feature',
            'billable' => true,
            'hourly_rate' => 100.0,
        ])
            ->assertStatus(201)
            ->assertJsonPath('duration_minutes', 120);
    });

    test('store validates required fields', function () {
        $this->postJson('/api/v1/projects/time-entries', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'started_at', 'ended_at']);
    });

    test('can show a time entry', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);

        $this->getJson("/api/v1/projects/time-entries/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('id', $entry->id);
    });

    test('can update a time entry', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);

        $this->putJson("/api/v1/projects/time-entries/{$entry->id}", [
            'description' => 'Updated description',
        ])
            ->assertOk()
            ->assertJsonPath('description', 'Updated description');
    });

    test('cannot update another user time entry as non-admin', function () {
        $other = User::factory()->create();
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $other->id,
        ]);

        $this->putJson("/api/v1/projects/time-entries/{$entry->id}", [
            'description' => 'Hack',
        ])->assertStatus(403);
    });

    test('can delete own time entry', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);

        $this->deleteJson("/api/v1/projects/time-entries/{$entry->id}")
            ->assertNoContent();

        expect(TimeEntry::find($entry->id))->toBeNull();
    });

    test('cannot delete another user time entry as non-admin', function () {
        $other = User::factory()->create();
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $other->id,
        ]);

        $this->deleteJson("/api/v1/projects/time-entries/{$entry->id}")
            ->assertStatus(403);
    });

    test('can start a timer', function () {
        $this->postJson('/api/v1/projects/time-entries/start', [
            'project_id' => $this->project->id,
            'description' => 'Starting work',
        ])
            ->assertStatus(201)
            ->assertJsonPath('ended_at', null);
    });

    test('start timer validates project_id', function () {
        $this->postJson('/api/v1/projects/time-entries/start', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    });

    test('can stop a running timer', function () {
        Carbon::setTestNow(Carbon::parse('2026-05-07 09:00:00'));
        $entry = TimeEntry::factory()->running()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'started_at' => Carbon::parse('2026-05-07 09:00:00'),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-07 10:00:00'));
        $this->postJson("/api/v1/projects/time-entries/{$entry->id}/stop")
            ->assertOk()
            ->assertJsonPath('duration_minutes', 60);
        Carbon::setTestNow();
    });

    test('stopping a non-running entry returns 422', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);

        $this->postJson("/api/v1/projects/time-entries/{$entry->id}/stop")
            ->assertStatus(422);
    });

    test('can mark a time entry as billed', function () {
        $entry = TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billed' => false,
        ]);

        $this->postJson("/api/v1/projects/time-entries/{$entry->id}/bill")
            ->assertOk()
            ->assertJsonPath('billed', true);
    });
});

// ---------------------------------------------------------------------------
// API: Project-scoped time entries & billing
// ---------------------------------------------------------------------------

describe('Project billing & time entries API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('employee');
        $this->project = Project::factory()->create();
    });

    test('can list time entries for a project', function () {
        TimeEntry::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);

        $this->getJson("/api/v1/projects/{$this->project->id}/time-entries")
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    });

    test('can get billing stats for a project', function () {
        TimeEntry::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'billed' => false,
            'duration_minutes' => 60,
            'hourly_rate' => 100.0,
        ]);

        $this->getJson("/api/v1/projects/{$this->project->id}/billing")
            ->assertOk()
            ->assertJsonStructure(['billing', 'stats' => [
                'total_hours',
                'billable_hours',
                'billable_amount',
                'billed_amount',
                'unbilled_amount',
            ]]);
    });

    test('can setup billing for a project', function () {
        $this->postJson("/api/v1/projects/{$this->project->id}/billing/setup", [
            'billing_type' => 'hourly',
            'hourly_rate' => 150.0,
            'budget_hours' => 200.0,
        ])
            ->assertStatus(201)
            ->assertJsonPath('billing_type', 'hourly');
    });

    test('setup billing validates billing_type', function () {
        $this->postJson("/api/v1/projects/{$this->project->id}/billing/setup", [
            'billing_type' => 'invalid_type',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['billing_type']);
    });

    test('can mark all billable entries as billed', function () {
        TimeEntry::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'billed' => false,
        ]);

        $this->postJson("/api/v1/projects/{$this->project->id}/billing/mark-billed")
            ->assertOk()
            ->assertJsonPath('marked_billed', 2);
    });
});
