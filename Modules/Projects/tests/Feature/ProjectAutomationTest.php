<?php

declare(strict_types=1);

use Modules\Projects\Models\AutomationRule;
use Modules\Projects\Models\Project;


it('creates an automation rule for a project', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $this
        ->postJson("/api/v1/projects/{$project->id}/automations", [
            'name' => 'Auto assign on creation',
            'trigger' => 'task_created',
            'conditions' => [['field' => 'priority', 'operator' => '=', 'value' => 'high']],
            'actions' => [['type' => 'assign_user', 'value' => $user->id]],
            'active' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Auto assign on creation')
        ->assertJsonPath('trigger', 'task_created')
        ->assertJsonPath('active', true);
});

it('lists automation rules for a project', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    AutomationRule::factory()->count(3)->create(['project_id' => $project->id]);

    $this
        ->getJson("/api/v1/projects/{$project->id}/automations")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('toggles an automation rule active/inactive', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $rule = AutomationRule::factory()->create([
        'project_id' => $project->id,
        'active' => true,
    ]);

    $this
        ->putJson("/api/v1/projects/{$project->id}/automations/{$rule->id}", [
            'active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('active', false);

    $this
        ->putJson("/api/v1/projects/{$project->id}/automations/{$rule->id}", [
            'active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('active', true);
});
