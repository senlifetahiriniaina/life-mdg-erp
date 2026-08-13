<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest cannot access protected endpoints', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    $this->postJson('/api/v1/auth/refresh')->assertUnauthorized();
});

test('user can register', function () {
    $this->postJson('/api/v1/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'Secret#12345',
        'password_confirmation' => 'Secret#12345',
    ])
        ->assertCreated()
        ->assertJsonStructure(['user' => ['id', 'email'], 'token']);
});

test('register requires name email and password', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('register rejects duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name'     => 'Someone',
        'email'    => 'taken@example.com',
        'password' => 'Secret#12345',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create(['password' => 'Secret#12345']);

    $this->postJson('/api/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'Secret#12345',
    ])
        ->assertOk()
        ->assertJsonStructure(['user', 'token']);
});

test('login fails with wrong password', function () {
     $user = actingAsUser('employee');

    $this->postJson('/api/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'wrongpassword',
    ])->assertUnprocessable();
});

test('login fails for unknown email', function () {
    $this->postJson('/api/v1/auth/login', [
        'email'    => 'nobody@example.com',
        'password' => 'password',
    ])->assertUnprocessable();
});

test('authenticated user can fetch own profile', function () {
     $user = actingAsUser('employee');
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonFragment(['email' => $user->email]);
});

test('authenticated user can logout', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    $user  = \App\Models\User::factory()->create()->assignRole('employee');
    $token = $user->createToken('test')->plainTextToken;

    expect($user->tokens()->count())->toBe(1);

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // token row deleted from DB
    expect($user->tokens()->count())->toBe(0);
});

test('authenticated user can refresh token', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    $user  = \App\Models\User::factory()->create()->assignRole('employee');
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->assertJsonStructure(['token']);

    $newToken = $response->json('token');
    expect($newToken)->not->toBe($token);

    // still exactly one active token (old replaced by new)
    expect($user->tokens()->count())->toBe(1);
});
