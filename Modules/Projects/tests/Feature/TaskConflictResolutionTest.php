<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Projects\Services\TaskConflictResolutionService;

uses(RefreshDatabase::class);

describe('Task Conflict Resolution Service', function () {
    beforeEach(function () {
        $this->service = app(TaskConflictResolutionService::class);
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->project = Project::factory()->create();
        $this->task = Task::factory()->create(['project_id' => $this->project->id]);
    });

    test('acquireLock creates a lock for a task', function () {
        $locked = $this->service->acquireLock($this->task->id, $this->user1->id);

        expect($locked)->not->toBeNull();
        expect($locked->id)->toBe($this->task->id);

        $lockInfo = $this->service->getLockInfo($this->task->id);
        expect($lockInfo['user_id'])->toBe($this->user1->id);
    });

    test('releaseLock removes a lock', function () {
        $this->service->acquireLock($this->task->id, $this->user1->id);

        $released = $this->service->releaseLock($this->task->id, $this->user1->id);

        expect($released)->toBeTrue();
        expect($this->service->getLockInfo($this->task->id))->toBeNull();
    });

    test('releaseLock fails if user does not own lock', function () {
        $this->service->acquireLock($this->task->id, $this->user1->id);

        $released = $this->service->releaseLock($this->task->id, $this->user2->id);

        expect($released)->toBeFalse();
        expect($this->service->getLockInfo($this->task->id))->not->toBeNull();
    });

    test('ownsLock returns true only for lock owner', function () {
        $this->service->acquireLock($this->task->id, $this->user1->id);

        expect($this->service->ownsLock($this->task->id, $this->user1->id))->toBeTrue();
        expect($this->service->ownsLock($this->task->id, $this->user2->id))->toBeFalse();
    });

    test('updateWithConflictDetection succeeds with no conflicts', function () {
        $data = ['title' => 'Updated Title', 'description' => 'New description'];
        $expectedValues = ['title' => $this->task->title];

        $result = $this->service->updateWithConflictDetection(
            $this->task,
            $data,
            $this->user1->id,
            $expectedValues
        );

        expect($result['success'])->toBeTrue();
        expect($result['task']->fresh()->title)->toBe('Updated Title');
        expect($result['conflicts'])->toBeEmpty();
    });

    test('updateWithConflictDetection detects conflicts', function () {
        // Simulate another user modifying the task
        $this->task->update(['title' => 'Changed by someone else']);

        $data = ['title' => 'My update'];
        $expectedValues = ['title' => 'Original title']; // Wrong expected value

        $result = $this->service->updateWithConflictDetection(
            $this->task,
            $data,
            $this->user1->id,
            $expectedValues
        );

        expect($result['success'])->toBeFalse();
        expect($result['conflicts'])->toHaveKey('title');
        expect($result['conflicts']['title']['current'])->toBe('Changed by someone else');
    });

    test('safeAtomicUpdate uses database transaction', function () {
        $data = ['status' => 'in_progress', 'description' => 'Working on it'];

        $result = $this->service->safeAtomicUpdate($this->task, $data, $this->user1->id);

        expect($result['success'])->toBeTrue();
        expect($result['task']->status)->toBe('in_progress');
        expect($result['task']->description)->toBe('Working on it');
    });

    test('mergeConflictingVersions uses incoming strategy', function () {
        $incomingData = ['title' => 'New title', 'priority' => 'high'];
        $conflicts = ['title' => ['expected' => 'Old', 'current' => 'Middle', 'incoming' => 'New']];

        $merged = $this->service->mergeConflictingVersions(
            $this->task,
            $incomingData,
            $conflicts,
            'incoming'
        );

        expect($merged['title'])->toBe('New title');
        expect($merged['priority'])->toBe('high');
    });

    test('mergeConflictingVersions uses current strategy', function () {
        $this->task->update(['title' => 'Current title', 'priority' => 'high']);
        $incomingData = ['title' => 'New title', 'priority' => 'low'];
        $conflicts = ['title' => ['expected' => 'Old', 'current' => 'Current', 'incoming' => 'New']];

        $merged = $this->service->mergeConflictingVersions(
            $this->task,
            $incomingData,
            $conflicts,
            'current'
        );

        expect($merged['title'])->toBe('Current title');
        expect($merged['priority'])->toBe('high'); // Unchanged
    });

    test('mergeConflictingVersions uses merge strategy', function () {
        $this->task->update(['title' => 'Current', 'priority' => 'high']);
        $incomingData = ['title' => 'New', 'description' => 'Added'];
        $conflicts = ['title' => ['expected' => 'Old', 'current' => 'Current', 'incoming' => 'New']];

        $merged = $this->service->mergeConflictingVersions(
            $this->task,
            $incomingData,
            $conflicts,
            'merge'
        );

        expect($merged['title'])->toBe('New'); // Conflict field uses incoming (last-write-wins)
        expect($merged['description'])->toBe('Added'); // Non-conflict field uses incoming
        expect($merged['priority'])->toBe('high'); // Unchanged field preserved
    });

    test('bulkUpdateWithConflicts handles multiple updates', function () {
        $task2 = Task::factory()->create(['project_id' => $this->project->id]);
        $task3 = Task::factory()->create(['project_id' => $this->project->id]);

        $updates = [
            [
                'task_id' => $this->task->id,
                'data' => ['title' => 'Task 1 updated'],
                'expectedValues' => ['title' => $this->task->title],
            ],
            [
                'task_id' => $task2->id,
                'data' => ['title' => 'Task 2 updated'],
                'expectedValues' => ['title' => 'Wrong expected'],
            ],
            [
                'task_id' => $task3->id,
                'data' => ['priority' => 'high'],
                'expectedValues' => [],
            ],
        ];

        $result = $this->service->bulkUpdateWithConflicts($updates, $this->user1->id);

        expect($result['successful'])->toBeGreaterThanOrEqual(2);
        expect($result['failed'])->toBeGreaterThanOrEqual(0);
    });

    test('getLockInfo returns null for unlocked task', function () {
        $lockInfo = $this->service->getLockInfo($this->task->id);

        expect($lockInfo)->toBeNull();
    });

    test('lock expires after timeout', function () {
        Carbon::setTestNow('2026-05-16 10:00:00');
        $this->service->acquireLock($this->task->id, $this->user1->id);

        // Check lock exists
        expect($this->service->getLockInfo($this->task->id))->not->toBeNull();

        // Fast forward 6 minutes (past 5 minute cache timeout)
        Carbon::setTestNow('2026-05-16 10:06:00');
        expect($this->service->getLockInfo($this->task->id))->toBeNull();

        Carbon::setTestNow();
    });
});

describe('Collaborative editing scenarios', function () {
    beforeEach(function () {
        $this->service = app(TaskConflictResolutionService::class);
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->project = Project::factory()->create();
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Original Title',
            'status' => 'open',
        ]);
    });

    test('two users can update different fields without conflict', function () {
        // User 1 updates title
        $result1 = $this->service->updateWithConflictDetection(
            $this->task,
            ['title' => 'User 1 Title'],
            $this->user1->id,
            ['title' => 'Original Title', 'status' => 'open']
        );
        expect($result1['success'])->toBeTrue();

        // User 2 updates status on same task snapshot
        $taskSnapshot = Task::find($this->task->id);
        $result2 = $this->service->updateWithConflictDetection(
            $taskSnapshot,
            ['status' => 'in_progress'],
            $this->user2->id,
            ['title' => 'Original Title', 'status' => 'open']
        );

        // User 2 should detect conflict on title but succeed on status
        expect($result2['success'])->toBeFalse();
        expect(array_key_exists('title', $result2['conflicts']))->toBeTrue();
    });

    test('last-write-wins resolves concurrent edits', function () {
        $taskSnapshot1 = $this->task->fresh();
        $taskSnapshot2 = $this->task->fresh();

        // User 1 tries to update
        $merged1 = $this->service->mergeConflictingVersions(
            $taskSnapshot1,
            ['title' => 'User 1 title'],
            [],
            'merge'
        );

        // User 2 tries to update (incoming wins)
        $merged2 = $this->service->mergeConflictingVersions(
            $taskSnapshot2,
            ['title' => 'User 2 title'],
            [],
            'merge'
        );

        // Both merges should prefer their own incoming data
        expect($merged1['title'])->toBe('User 1 title');
        expect($merged2['title'])->toBe('User 2 title');
    });

    test('atomic transaction ensures consistency', function () {
        $data = [
            'title' => 'Atomic Update',
            'status' => 'in_progress',
            'estimated_hours' => 10,
        ];

        $result = $this->service->safeAtomicUpdate($this->task, $data, $this->user1->id);

        expect($result['success'])->toBeTrue();

        $fresh = Task::find($this->task->id);
        expect($fresh->title)->toBe('Atomic Update');
        expect($fresh->status)->toBe('in_progress');
        expect($fresh->estimated_hours)->toBe(10);
    });
});
