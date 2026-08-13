<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\BI\Models\Kpi;
use Modules\BI\Models\Report;

uses(RefreshDatabase::class);

test('guest is redirected from bi index', function () {
    $this->get('/bi')->assertRedirect('/login');
});

test('guest is redirected from bi reports', function () {
    $this->get('/bi/reports')->assertRedirect('/login');
});

test('authenticated user sees bi index with kpis and recentReports props', function () {
    $user = User::factory()->create();
    Kpi::factory()->count(2)->create();
    Report::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/bi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BI/Index')
            ->has('kpis')
            ->has('recentReports')
        );
});

test('authenticated user sees bi reports page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/bi/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BI/Reports/Index')
            ->has('reports')
        );
});
