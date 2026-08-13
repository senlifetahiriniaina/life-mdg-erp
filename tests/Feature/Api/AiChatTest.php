<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns ai chat response', function () {
     $user = actingAsUser('employee');
                $response = $this
        ->postJson('/api/v1/ai/chat', ['message' => 'Bonjour', 'module' => 'CRM'])
        ->assertStatus(200)
        ->assertJsonStructure(['reply', 'module', 'actions']);
});

it('requires message', function () {
     $user = actingAsUser('employee');
                $response = $this
        ->postJson('/api/v1/ai/chat', [])
        ->assertStatus(422);
});

it('requires authentication', function () {
    $this->postJson('/api/v1/ai/chat', ['message' => 'test'])
        ->assertStatus(401);
});
