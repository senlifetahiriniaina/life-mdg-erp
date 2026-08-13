<?php

declare(strict_types=1);

use Modules\Projects\Models\Epic;
use Modules\Projects\Models\Project;


it('lists epics for a project', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    Epic::factory()->count(3)->create(['project_id' => $project->id]);

    $this
        ->getJson("/api/v1/projects/{$project->id}/epics")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates an epic for a project', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();

    $this
        ->postJson("/api/v1/projects/{$project->id}/epics", [
            'title' => 'User Authentication Epic',
            'color' => '#6366f1',
            'status' => 'open',
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-31',
        ])
        ->assertStatus(201)
        ->assertJsonPath('title', 'User Authentication Epic')
        ->assertJsonPath('project_id', $project->id);
});

it('updates an epic', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id, 'status' => 'open']);

    $this
        ->putJson("/api/v1/projects/{$project->id}/epics/{$epic->id}", [
            'status' => 'in_progress',
            'color' => '#10b981',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress')
        ->assertJsonPath('color', '#10b981');
});

it('deletes an epic', function (): void {
    $user = actingAsUser('employee');
    $project = Project::factory()->create();
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    $this
        ->deleteJson("/api/v1/projects/{$project->id}/epics/{$epic->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('prj_epics', ['id' => $epic->id]);
});
