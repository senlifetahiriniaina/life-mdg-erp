<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Accounting\Models\Invoice;

uses(RefreshDatabase::class);

test('guest is redirected to login from invoices index', function () {
    $this->get('/accounting/invoices')->assertRedirect('/login');
});

test('authenticated user sees invoices index', function () {
    $user = User::factory()->create();
    Invoice::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get('/accounting/invoices')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Invoices/Index')
            ->has('invoices')
        );
});

test('invoices index returns paginated invoices', function () {
    $user = User::factory()->create();
    Invoice::factory()->count(2)->create(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get('/accounting/invoices')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Invoices/Index')
            ->has('invoices.data', 2)
        );
});

test('status filter returns matching invoices', function () {
    $user = User::factory()->create();
    Invoice::factory()->create(['created_by' => $user->id, 'status' => 'draft']);
    Invoice::factory()->create(['created_by' => $user->id, 'status' => 'paid']);

    $this->actingAs($user)
        ->get('/accounting/invoices?status=draft')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Invoices/Index')
            ->has('invoices.data', 1)
        );
});

test('status filter returns only paid invoices', function () {
    $user = User::factory()->create();
    Invoice::factory()->create(['created_by' => $user->id, 'status' => 'draft']);
    Invoice::factory()->create(['created_by' => $user->id, 'status' => 'paid']);

    $this->actingAs($user)
        ->get('/accounting/invoices?status=paid')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Accounting/Invoices/Index')
            ->has('invoices.data', 1)
        );
});
