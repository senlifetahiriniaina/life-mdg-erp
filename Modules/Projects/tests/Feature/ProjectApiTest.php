<?php

declare(strict_types=1);

use Modules\Projects\Models\Project;


test('authenticated user can list projects', function () {
    $user = actingAsUser('employee');
    $this
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('authenticated user can create a project', function () {
    $user = actingAsUser('employee');
    $this
        ->postJson('/api/v1/projects', [
            'name' => 'Website Redesign',
            'status' => 'active',
            'is_billable' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Website Redesign');
});

test('authenticated user can delete a project', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $this
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNoContent();
});

test('unauthenticated user cannot list projects', function () {
    $this->getJson('/api/v1/projects')
        ->assertUnauthorized();
});
