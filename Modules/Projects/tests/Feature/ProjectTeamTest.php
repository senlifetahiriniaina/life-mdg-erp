<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTeamMember;
use Modules\Projects\Models\ProjectTimeLog;


test('can add member to project', function () {
    $owner = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->create();

    $this
        ->postJson("/api/v1/projects/{$project->id}/team", [
            'user_id' => $user->id,
            'role' => 'member',
        ])
        ->assertStatus(201)
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('role', 'member');

    $this->assertDatabaseHas('prj_team_members', [
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => 'member',
    ]);
});

test('can remove member from project', function () {
    $owner = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->create();

    $member = ProjectTeamMember::create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $this
        ->deleteJson("/api/v1/projects/{$project->id}/team/{$member->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('prj_team_members', [
        'id' => $member->id,
        'user_id' => $user->id,
    ]);

    // left_at should be set
    $updated = ProjectTeamMember::find($member->id);
    expect($updated?->left_at)->not->toBeNull();
});

test('can log time manually', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $payload = [
        'started_at' => now()->subHour()->toISOString(),
        'ended_at' => now()->toISOString(),
        'description' => 'Working on feature X',
        'billable' => true,
    ];

    $response = $this
        ->postJson("/api/v1/projects/{$project->id}/time-logs", $payload)
        ->assertStatus(201)
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('billable', true);

    $data = $response->json();
    expect($data['duration_minutes'])->toBeGreaterThan(0);
});

test('can start and stop a timer', function () {
    $user = actingAsUser('employee');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // Start timer
    $startResponse = $this
        ->postJson("/api/v1/projects/{$project->id}/time-logs", [
            'started_at' => now()->toISOString(),
        ])
        ->assertStatus(201);

    $logId = $startResponse->json('id');
    expect($startResponse->json('ended_at'))->toBeNull();

    // Stop timer
    $this
        ->postJson("/api/v1/projects/time-logs/{$logId}/stop")
        ->assertOk()
        ->assertJsonPath('id', $logId);

    $log = ProjectTimeLog::find($logId);
    expect($log?->ended_at)->not->toBeNull();
});

test('unauthenticated user gets 401 on team endpoints', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/v1/projects/{$project->id}/team")->assertUnauthorized();
    $this->postJson("/api/v1/projects/{$project->id}/team", [])->assertUnauthorized();
    $this->getJson("/api/v1/projects/{$project->id}/time-logs")->assertUnauthorized();
    $this->postJson("/api/v1/projects/{$project->id}/time-logs", [])->assertUnauthorized();
});
