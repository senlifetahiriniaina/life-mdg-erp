<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest is redirected to login from dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('renders dashboard page for admin', function () {
    $user = actingAsUser('admin');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('metrics')
            ->has('insights')
            ->where('metrics.role', 'admin')
        );
});

it('renders dashboard page for sales rep', function () {
    $user = actingAsUser('sales-rep');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('metrics')
            ->has('insights')
            ->where('metrics.role', 'sales-rep')
        );
});

it('renders dashboard page for hr-manager', function () {
    $user = actingAsUser('hr-manager');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('metrics')
            ->has('insights')
            ->where('metrics.role', 'hr-manager')
        );
});

it('renders dashboard page for accountant', function () {
    $user = actingAsUser('accountant');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('metrics')
            ->has('insights')
            ->where('metrics.role', 'accountant')
        );
});

it('renders dashboard page for employee', function () {
    $user = actingAsUser('employee');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('metrics')
            ->has('insights')
            ->where('metrics.role', 'employee')
        );
});

it('insights prop contains at least one insight with required keys', function () {
    $user = actingAsUser('admin');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('insights')
            ->has('insights.0', fn (Assert $item) => $item
                ->has('type')
                ->has('module')
                ->has('title')
                ->has('description')
                ->has('action_label')
                ->has('action_route')
                ->has('priority')
                ->etc()
            )
        );
});
