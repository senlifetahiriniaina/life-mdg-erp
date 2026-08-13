<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TaskDependency;
use Modules\Projects\Services\DependencyCycleDetectionService;

uses(RefreshDatabase::class);

describe('Cycle Detection Service', function () {
    beforeEach(function () {
        $this->service = app(DependencyCycleDetectionService::class);
        $this->project = Project::factory()->create();
    });

    test('self-dependency is detected as cycle', function () {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $result = $this->service->checkCycleOnAdd($task, $task);

        expect($result['success'])->toBeFalse();
        expect($result['cycle'])->toBeTrue();
        expect($result['message'])->toContain('cannot depend on itself');
    });

    test('direct two-task cycle is detected', function () {
        $taskA = Task::factory()->create(['project_id' => $this->project->id]);
        $taskB = Task::factory()->create(['project_id' => $this->project->id]);

        // Create: A depends on B
        TaskDependency::create([
            'task_id' => $taskA->id,
            'depends_on_task_id' => $taskB->id,
        ]);

        // Try to create: B depends on A (would create cycle)
        $result = $this->service->checkCycleOnAdd($taskB, $taskA);

        expect($result['success'])->toBeFalse();
        expect($result['cycle'])->toBeTrue();
    });

    test('three-task circular dependency is detected', function () {
        $taskA = Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Task A']);
        $taskB = Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Task B']);
        $taskC = Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Task C']);

        // Create chain: A -> B -> C
        TaskDependency::create(['task_id' => $taskA->id, 'depends_on_task_id' => $taskB->id]);
        TaskDependency::create(['task_id' => $taskB->id, 'depends_on_task_id' => $taskC->id]);

        // Try to create: C -> A (would create cycle: A -> B -> C -> A)
        $result = $this->service->checkCycleOnAdd($taskC, $taskA);

        expect($result['success'])->toBeFalse();
        expect($result['cycle'])->toBeTrue();
    });

    test('non-cyclic dependency is allowed', function () {
        $taskA = Task::factory()->create(['project_id' => $this->project->id]);
        $taskB = Task::factory()->create(['project_id' => $this->project->id]);
        $taskC = Task::factory()->create(['project_id' => $this->project->id]);

        // A -> B
        TaskDependency::create(['task_id' => $taskA->id, 'depends_on_task_id' => $taskB->id]);

        // B -> C should be allowed (creates A -> B -> C, no cycle)
        $result = $this->service->checkCycleOnAdd($taskB, $taskC);

        expect($result['success'])->toBeTrue();
        expect($result['cycle'])->toBeFalse();
    });

    test('getAllDependencies returns all transitive dependencies', function () {
        $taskA = Task::factory()->create(['project_id' => $this->project->id]);
        $taskB = Task::factory()->create(['project_id' => $this->project->id]);
        $taskC = Task::factory()->create(['project_id' => $this->project->id]);
        $taskD = Task::factory()->create(['project_id' => $this->project->id]);

        // Create: A -> B -> C -> D
        TaskDependency::create(['task_id' => $taskA->id, 'depends_on_task_id' => $taskB->id]);
        TaskDependency::create(['task_id' => $taskB->id, 'depends_on_task_id' => $taskC->id]);
        TaskDependency::create(['task_id' => $taskC->id, 'depends_on_task_id' => $taskD->id]);

        $deps = $this->service->getAllDependencies($taskA);

        expect($deps)->toContain($taskB->id);
        expect($deps)->toContain($taskC->id);
        expect($deps)->toContain($taskD->id);
        expect(count($deps))->toBe(3);
    });

    test('getMaxChainDepth respects max depth limit', function () {
        $tasks = [];
        for ($i = 0; $i < 15; $i++) {
            $tasks[$i] = Task::factory()->create(['project_id' => $this->project->id]);
        }

        // Create chain: 0 -> 1 -> 2 -> ... -> 14 (15 tasks = 14 dependencies)
        for ($i = 0; $i < 14; $i++) {
            TaskDependency::create([
                'task_id' => $tasks[$i]->id,
                'depends_on_task_id' => $tasks[$i + 1]->id,
            ]);
        }

        $depth = $this->service->getMaxChainDepth($tasks[0]);

        // Should be capped at reasonable depth
        expect($depth)->toBeLessThanOrEqual(DependencyCycleDetectionService::MAX_CHAIN_DEPTH);
    });

    test('hasCyclicPath correctly identifies cycles', function () {
        $taskA = Task::factory()->create(['project_id' => $this->project->id]);
        $taskB = Task::factory()->create(['project_id' => $this->project->id]);
        $taskC = Task::factory()->create(['project_id' => $this->project->id]);

        // Create: A -> B -> C
        TaskDependency::create(['task_id' => $taskA->id, 'depends_on_task_id' => $taskB->id]);
        TaskDependency::create(['task_id' => $taskB->id, 'depends_on_task_id' => $taskC->id]);

        // A depends on C (indirectly)
        expect($this->service->hasCyclicPath($taskA, $taskC))->toBeTrue();

        // C does not depend on A
        expect($this->service->hasCyclicPath($taskC, $taskA))->toBeFalse();
    });

    test('getCriticalPath returns longest dependency chain', function () {
        $taskA = Task::factory()->create(['project_id' => $this->project->id]);
        $taskB = Task::factory()->create(['project_id' => $this->project->id]);
        $taskC = Task::factory()->create(['project_id' => $this->project->id]);
        $taskD = Task::factory()->create(['project_id' => $this->project->id]);

        // Create: A -> B -> C (3 nodes)
        //         A -> D     (2 nodes)
        TaskDependency::create(['task_id' => $taskA->id, 'depends_on_task_id' => $taskB->id]);
        TaskDependency::create(['task_id' => $taskB->id, 'depends_on_task_id' => $taskC->id]);
        TaskDependency::create(['task_id' => $taskA->id, 'depends_on_task_id' => $taskD->id]);

        $path = $this->service->getCriticalPath($taskA);

        expect($path[0])->toBe($taskA->id);
        expect(in_array($taskC->id, $path))->toBeTrue();
        expect(count($path))->toBeGreaterThanOrEqual(3); // At least A, B, C
    });
});

// Test model-level cycle detection integration
describe('Task dependency cycle detection integration', function () {
    beforeEach(function () {
        $this->service = app(DependencyCycleDetectionService::class);
        $this->project = Project::factory()->create();
    });

    test('cannot create dependency that creates a cycle', function () {
        $taskA = Task::factory()->create(['project_id' => $this->project->id]);
        $taskB = Task::factory()->create(['project_id' => $this->project->id]);

        TaskDependency::create(['task_id' => $taskA->id, 'depends_on_task_id' => $taskB->id]);

        // Verify cycle is detected
        $result = $this->service->checkCycleOnAdd($taskB, $taskA);
        expect($result['cycle'])->toBeTrue();

        // Try to create anyway - should fail
        $this->expectException(\Exception::class);
        TaskDependency::create(['task_id' => $taskB->id, 'depends_on_task_id' => $taskA->id]);
    });

    test('can create multiple independent dependency chains', function () {
        // Chain 1: A1 -> B1 -> C1
        $a1 = Task::factory()->create(['project_id' => $this->project->id]);
        $b1 = Task::factory()->create(['project_id' => $this->project->id]);
        $c1 = Task::factory()->create(['project_id' => $this->project->id]);

        // Chain 2: A2 -> B2 -> C2
        $a2 = Task::factory()->create(['project_id' => $this->project->id]);
        $b2 = Task::factory()->create(['project_id' => $this->project->id]);
        $c2 = Task::factory()->create(['project_id' => $this->project->id]);

        // Create both chains
        TaskDependency::create(['task_id' => $a1->id, 'depends_on_task_id' => $b1->id]);
        TaskDependency::create(['task_id' => $b1->id, 'depends_on_task_id' => $c1->id]);

        TaskDependency::create(['task_id' => $a2->id, 'depends_on_task_id' => $b2->id]);
        TaskDependency::create(['task_id' => $b2->id, 'depends_on_task_id' => $c2->id]);

        // Verify each chain has correct dependencies
        expect($this->service->getAllDependencies($a1))->toHaveCount(2);
        expect($this->service->getAllDependencies($a2))->toHaveCount(2);

        // Chains should not interfere
        expect($this->service->hasCyclicPath($a1, $a2))->toBeFalse();
        expect($this->service->hasCyclicPath($a2, $a1))->toBeFalse();
    });
});
