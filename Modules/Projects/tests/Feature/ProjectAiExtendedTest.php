<?php

declare(strict_types=1);
use Modules\Projects\Services\AI\ProjectsAIService;


test('can generate tasks from specification', function () {
    actingAsUser('employee');

    $mock = Mockery::mock(ProjectsAIService::class);
    $mock->shouldReceive('generateTasksFromSpec')
        ->once()
        ->andReturn(['generated_tasks' => '{"tasks":[]}', 'project_id' => 1]);
    app()->instance(ProjectsAIService::class, $mock);

    $this->postJson('/api/v1/projects/ai/generate-tasks', [
        'project_id' => 1,
        'specification' => 'Build a user authentication module with login, registration, password reset, and 2FA support.',
    ])->assertOk()->assertJsonStructure(['generated_tasks', 'project_id']);
});

test('can suggest task prioritization', function () {
    actingAsUser('employee');

    $mock = Mockery::mock(ProjectsAIService::class);
    $mock->shouldReceive('suggestPrioritization')
        ->once()
        ->andReturn(['prioritization' => '{"prioritized_tasks":[]}', 'project_id' => 1]);
    app()->instance(ProjectsAIService::class, $mock);

    $this->postJson('/api/v1/projects/ai/suggest-prioritization', [
        'project_id' => 1,
        'tasks' => [
            ['task_id' => 1, 'title' => 'Setup DB schema',   'estimated_hours' => 4],
            ['task_id' => 2, 'title' => 'Build API endpoints', 'estimated_hours' => 8],
            ['task_id' => 3, 'title' => 'Write unit tests',  'estimated_hours' => 6],
        ],
    ])->assertOk()->assertJsonStructure(['prioritization', 'project_id']);
});
