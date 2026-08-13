<?php

declare(strict_types=1);

namespace Modules\Projects\Services\AI;

use Modules\Core\Services\AI\AIService;

class ProjectsAIService
{
    public function __construct(private readonly AIService $ai) {}

    public function estimateTask(array $taskData, array $similarTasks = []): array
    {
        $response = $this->ai->ask(
            'Estimate the effort required for this task based on its description and similar completed tasks. Return valid JSON only: {"estimated_hours":X,"confidence":"high|medium|low","complexity":"simple|medium|complex","breakdown":{"design":X,"development":X,"testing":X},"assumptions":["..."]}',
            ['task' => json_encode($taskData), 'similar_tasks' => json_encode($similarTasks)],
            'Projects'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['estimated_hours' => null, 'raw' => $response];
    }

    public function identifyRisks(array $projectData): array
    {
        $response = $this->ai->ask(
            'Analyze this project and identify the top risks that could impact delivery, budget, or quality. Return valid JSON only: {"risks":[{"title":"...","category":"scope|resource|technical|external","probability":"high|medium|low","impact":"high|medium|low","mitigation":"..."}]}',
            ['project' => json_encode($projectData)],
            'Projects'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['risks' => [], 'raw' => $response];
    }

    public function generateStatusReport(array $projectData, array $completedTasks, array $pendingTasks): string
    {
        return $this->ai->ask(
            'Generate a concise project status report (3-4 paragraphs) covering: overall health, key achievements this period, upcoming milestones, and blockers.',
            [
                'project' => json_encode($projectData),
                'completed_tasks' => json_encode($completedTasks),
                'pending_tasks' => json_encode($pendingTasks),
            ],
            'Projects'
        );
    }

    public function generateTasksFromSpec(int $projectId, string $specification): array
    {
        $prompt = 'Break down this project specification into actionable tasks. Return JSON with: tasks (array of {title, description, estimated_hours, priority (high/medium/low), dependencies (array of task indices), suggested_assignee_role}), epic_suggestions (array), total_estimated_hours (int).';
        $result = $this->ai->ask($prompt, ['specification' => $specification], 'Projects', 'en');

        return ['generated_tasks' => $result, 'project_id' => $projectId];
    }

    public function suggestPrioritization(int $projectId, array $tasks): array
    {
        $prompt = 'Analyze these project tasks and suggest an optimal prioritization order. Return JSON with: prioritized_tasks (array of {task_id, priority_rank, reasoning}), critical_path (array of task_ids), bottlenecks (array), sprint_suggestions (array of {sprint_number, task_ids, rationale}).';
        $result = $this->ai->ask($prompt, ['tasks' => json_encode($tasks)], 'Projects', 'en');

        return ['prioritization' => $result, 'project_id' => $projectId];
    }
}
