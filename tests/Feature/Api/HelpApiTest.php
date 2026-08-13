<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns help content for a known context key', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/help/context?key=crm.contacts')
        ->assertOk()
        ->assertJsonStructure(['title', 'summary', 'tips', 'docs_url'])
        ->assertJsonPath('title', 'CRM — Contacts');
});

it('returns a fallback for an unknown context key', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/help/context?key=unknown.page')
        ->assertOk()
        ->assertJsonPath('tips', []);
});

it('returns 422 when key is missing', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/help/context')
        ->assertUnprocessable();
});

it('returns the full help index', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/help')
        ->assertOk()
        ->assertJsonStructure(['data' => [['key', 'title']]]);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/help/context?key=crm.contacts')
        ->assertUnauthorized();
});
