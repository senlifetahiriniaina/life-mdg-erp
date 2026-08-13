<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Projects\Models\Project;

uses(RefreshDatabase::class);

test('guest is redirected from projects index', function () {
    $this->get('/projects')->assertRedirect('/login');
});

test('guest is redirected from projects show', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->get("/projects/{$project->id}")->assertRedirect('/login');
});

test('authenticated user sees projects index', function () {
    $user = User::factory()->create();
    Project::factory()->count(3)->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects')
        );
});

test('authenticated user sees project show with tasks and milestones props', function () {
    $user    = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->has('project')
        );
});

test('show returns 404 for non-existent project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/projects/99999')
        ->assertNotFound();
});
