<?php

declare(strict_types=1);

use Modules\Projects\Models\Project;


test('can get report data as JSON', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this
        ->getJson("/api/v1/projects/{$project->id}/report")
        ->assertOk()
        ->assertJsonStructure([
            'project',
            'progress',
            'task_summary',
            'team_hours',
            'total_hours',
            'milestones',
            'budget',
            'generated_at',
        ]);
});

test('can download PDF report', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = $this
        ->get("/api/v1/projects/{$project->id}/report/pdf");

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('unauthenticated user gets 401 on report endpoints', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/v1/projects/{$project->id}/report")->assertUnauthorized();
    $this->getJson("/api/v1/projects/{$project->id}/report/pdf")->assertUnauthorized();
});

test('non-owner can view report if authenticated', function () {
    $owner = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $other = actingAsUser('employee');

    // By default viewAny/view are open to authenticated users (BaseErpPolicy)
    $this
        ->getJson("/api/v1/projects/{$project->id}/report")
        ->assertOk();
});
