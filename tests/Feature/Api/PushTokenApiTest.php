<?php

declare(strict_types=1);

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can register a push token', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/push-tokens', [
            'token'       => 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]',
            'platform'    => 'expo',
            'device_name' => 'iPhone 15 Pro',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Token registered');

    expect(PushToken::where('user_id', $user->id)->count())->toBe(1);
});

test('registering the same token again is idempotent', function () {
    actingAsUser('employee');
    $token = 'ExponentPushToken[idempotent_token]';

    $this->postJson('/api/v1/push-tokens', ['token' => $token, 'platform' => 'expo'])->assertSuccessful();
    $this->postJson('/api/v1/push-tokens', ['token' => $token, 'platform' => 'expo'])->assertSuccessful();

    expect(PushToken::where('token', $token)->count())->toBe(1);
});

test('token defaults to expo platform when platform is omitted', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[platform_default]'])
        ->assertOk();

    expect(PushToken::where('user_id', $user->id)->first()->platform)->toBe('expo');
});

test('token registration fails without a token value', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/push-tokens', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

test('authenticated user can deregister a push token', function () {
     $user = actingAsUser('employee');
    $token = 'ExponentPushToken[to_be_deleted]';

    PushToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'expo']);
        $response = $this
        ->deleteJson('/api/v1/push-tokens', ['token' => $token])
        ->assertStatus(204);

    expect(PushToken::where('token', $token)->exists())->toBeFalse();
});

test('deregistering a non-existent token is a no-op', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->deleteJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[ghost]'])
        ->assertStatus(204);
});

test('user cannot deregister another users token', function () {
    $owner  = User::factory()->create();
    $other  = User::factory()->create();
    $token  = 'ExponentPushToken[not_mine]';

    PushToken::create(['user_id' => $owner->id, 'token' => $token, 'platform' => 'expo']);

    $this->actingAs($other, 'sanctum')
        ->deleteJson('/api/v1/push-tokens', ['token' => $token])
        ->assertStatus(204);

    // Token still belongs to the original owner
    expect(PushToken::where('token', $token)->exists())->toBeTrue();
});

test('unauthenticated user cannot register a token', function () {
    $this->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[unauth]'])
        ->assertUnauthorized();
});
