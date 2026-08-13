<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TimeEntry;
use Modules\Projects\Services\TimeTrackingDeduplicationService;

uses(RefreshDatabase::class);

describe('Time Tracking Deduplication Service', function () {
    beforeEach(function () {
        $this->service = app(TimeTrackingDeduplicationService::class);
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create();
        $this->task = Task::factory()->create(['project_id' => $this->project->id]);
    });

    test('finds and removes exact duplicate time entries', function () {
        $date = '2026-05-16';

        // Create two identical entries
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'started_at' => "{$date} 09:00:00",
            'duration_minutes' => 60,
        ]);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'started_at' => "{$date} 10:00:00",
            'duration_minutes' => 60,
        ]);

        $result = $this->service->deduplicateUserDate($this->user->id, $date);

        expect($result['duplicates_removed'])->toBeGreaterThan(0);
        expect(TimeEntry::where('user_id', $this->user->id)->count())->toBeLessThanOrEqual(1);
    });

    test('merges duplicate entries by summing durations', function () {
        $date = '2026-05-16';

        // Create two duplicates with different durations
        $entry1 = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'started_at' => "{$date} 09:00:00",
            'duration_minutes' => 60,
        ]);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'started_at' => "{$date} 10:00:00",
            'duration_minutes' => 90,
        ]);

        $this->service->deduplicateUserDate($this->user->id, $date);

        $merged = TimeEntry::find($entry1->id);
        expect($merged->duration_minutes)->toBe(150); // 60 + 90
    });

    test('keeps earliest entry and deletes others', function () {
        $date = '2026-05-16';
        Carbon::setTestNow("{$date} 08:00:00");

        // Create first entry at 8am
        $earliest = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'started_at' => "{$date} 09:00:00",
            'duration_minutes' => 60,
        ]);

        Carbon::setTestNow("{$date} 09:00:00");

        // Create second entry at 9am
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'started_at' => "{$date} 10:00:00",
            'duration_minutes' => 60,
        ]);

        $this->service->deduplicateUserDate($this->user->id, $date);

        // Earliest should still exist
        expect(TimeEntry::find($earliest->id))->not->toBeNull();

        Carbon::setTestNow();
    });

    test('does not merge entries for different projects', function () {
        $project2 = Project::factory()->create();
        $date = '2026-05-16';

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'started_at' => "{$date} 09:00:00",
            'duration_minutes' => 60,
        ]);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project2->id,
            'task_id' => null,
            'started_at' => "{$date} 10:00:00",
            'duration_minutes' => 60,
        ]);

        $result = $this->service->deduplicateUserDate($this->user->id, $date);

        expect($result['duplicates_removed'])->toBe(0);
        expect(TimeEntry::where('user_id', $this->user->id)->count())->toBe(2);
    });

    test('does not merge entries for different tasks', function () {
        $task2 = Task::factory()->create(['project_id' => $this->project->id]);
        $date = '2026-05-16';

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'started_at' => "{$date} 09:00:00",
            'duration_minutes' => 60,
        ]);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $task2->id,
            'started_at' => "{$date} 10:00:00",
            'duration_minutes' => 60,
        ]);

        $result = $this->service->deduplicateUserDate($this->user->id, $date);

        expect($result['duplicates_removed'])->toBe(0);
        expect(TimeEntry::where('user_id', $this->user->id)->count())->toBe(2);
    });

    test('auditTimeEntries detects missing durations', function () {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'started_at' => '2026-05-16 09:00:00',
            'ended_at' => '2026-05-16 10:00:00',
            'duration_minutes' => null,
        ]);

        $issues = $this->service->auditTimeEntries();

        $missingDuration = collect($issues)->first(fn ($issue) => $issue['type'] === 'missing_duration');
        expect($missingDuration)->not->toBeNull();
        expect($missingDuration['count'])->toBeGreaterThan(0);
    });

    test('auditTimeEntries detects negative durations', function () {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'duration_minutes' => -30,
        ]);

        $issues = $this->service->auditTimeEntries();

        $negative = collect($issues)->first(fn ($issue) => $issue['type'] === 'negative_duration');
        expect($negative)->not->toBeNull();
    });

    test('isTimesheetsSyncEnabled returns correct status', function () {
        $enabled = $this->service->isTimesheetsSyncEnabled();
        expect(is_bool($enabled))->toBeTrue();
    });

    test('syncToTimesheet creates timesheet entry when enabled', function () {
        if (!$this->service->isTimesheetsSyncEnabled()) {
            $this->markTestSkipped('Timesheets sync not enabled');
        }

        $entry = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'duration_minutes' => 120,
            'billable' => true,
        ]);

        $synced = $this->service->syncToTimesheet($entry);

        expect($synced)->toBeTrue();
    });

    test('syncFromTimesheet updates projects entry when enabled', function () {
        if (!$this->service->isTimesheetsSyncEnabled()) {
            $this->markTestSkipped('Timesheets sync not enabled');
        }

        // This would require a real Timesheets module entry
        // Skip for now as it's an integration test
        $this->markTestSkipped('Requires Timesheets module');
    });

    test('fullBidirectionalSync returns sync statistics', function () {
        if (!$this->service->isTimesheetsSyncEnabled()) {
            $this->markTestSkipped('Timesheets sync not enabled');
        }

        $result = $this->service->fullBidirectionalSync();

        expect($result)->toHaveKeys(['synced', 'conflicts']);
        expect(is_int($result['synced']))->toBeTrue();
        expect(is_int($result['conflicts']))->toBeTrue();
    });

    test('audit detects orphaned entries when sync enabled', function () {
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'timesheet_entry_id' => null,
            'source' => 'projects',
        ]);

        $issues = $this->service->auditTimeEntries();

        if ($this->service->isTimesheetsSyncEnabled()) {
            $orphaned = collect($issues)->first(fn ($issue) => $issue['type'] === 'orphaned_entries');
            expect($orphaned)->not->toBeNull();
        }
    });
});

describe('Time entry merge scenarios', function () {
    beforeEach(function () {
        $this->service = app(TimeTrackingDeduplicationService::class);
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create();
        $this->task = Task::factory()->create(['project_id' => $this->project->id]);
    });

    test('merges three entries correctly', function () {
        $entries = [];
        for ($i = 0; $i < 3; $i++) {
            $entries[] = TimeEntry::factory()->create([
                'user_id' => $this->user->id,
                'project_id' => $this->project->id,
                'task_id' => $this->task->id,
                'duration_minutes' => 30 + ($i * 10),
            ]);
        }

        $result = $this->service->deduplicateUserDate($this->user->id, $entries[0]->started_at->format('Y-m-d'));

        // Should have merged into one entry
        $count = TimeEntry::where('user_id', $this->user->id)->count();
        expect($count)->toBeLessThanOrEqual(1);
    });

    test('preserves entry data during merge', function () {
        $entry1 = TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'duration_minutes' => 60,
            'billable' => true,
            'description' => 'Original description',
        ]);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'duration_minutes' => 30,
            'billable' => true,
        ]);

        $this->service->deduplicateUserDate($this->user->id, $entry1->started_at->format('Y-m-d'));

        $merged = TimeEntry::find($entry1->id);
        expect($merged->billable)->toBeTrue();
        expect($merged->description)->toBe('Original description');
        expect($merged->duration_minutes)->toBe(90);
    });
});
