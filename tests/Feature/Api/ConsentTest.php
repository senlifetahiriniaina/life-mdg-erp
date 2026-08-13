<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('authenticated user can update consent preferences', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->putJson('/api/v1/consent', [
            'cookie_consent'    => true,
            'marketing_consent' => false,
        ])
        ->assertStatus(200)
        ->assertJson(['message' => 'Consent updated.']);

    $user->refresh();

    expect($user->cookie_consent)->toBeTrue()
        ->and($user->marketing_consent)->toBeFalse()
        ->and($user->cookie_consent_at)->not->toBeNull()
        ->and($user->marketing_consent_at)->not->toBeNull();
});

it('unauthenticated user cannot update consent', function () {
    $this->putJson('/api/v1/consent', [
        'cookie_consent'    => true,
        'marketing_consent' => true,
    ])->assertStatus(401);
});
