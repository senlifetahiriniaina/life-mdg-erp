<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('login page is accessible as guest', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

test('authenticated user is redirected away from login', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/login')->assertRedirect();
});

test('valid credentials redirect to dashboard', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('invalid credentials return validation error', function () {
    User::factory()->create(['email' => 'test@test.com', 'password' => bcrypt('correct')]);
    $this->post('/login', ['email' => 'test@test.com', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
});

test('logout invalidates session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

test('forgot password page is accessible as guest', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
});

test('guest is redirected from profile page', function () {
    $this->get('/profile')->assertRedirect('/login');
});

test('authenticated user sees profile page', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Profile')
            ->has('user')
        );
});

test('guest is redirected from settings page', function () {
    $this->get('/settings')->assertRedirect('/login');
});

test('authenticated user sees settings page', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Index'));
});
