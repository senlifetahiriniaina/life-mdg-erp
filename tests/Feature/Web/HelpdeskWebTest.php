<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Helpdesk\Models\Ticket;

uses(RefreshDatabase::class);

test('guest is redirected from helpdesk tickets index', function () {
    $this->get('/helpdesk/tickets')->assertRedirect('/login');
});

test('guest is redirected from helpdesk tickets show', function () {
    $user   = User::factory()->create();
    $ticket = Ticket::factory()->create(['reporter_id' => $user->id]);

    $this->get("/helpdesk/tickets/{$ticket->id}")->assertRedirect('/login');
});

test('authenticated user sees tickets index', function () {
    $user = User::factory()->create();
    Ticket::factory()->count(3)->create(['reporter_id' => $user->id]);

    $this->actingAs($user)
        ->get('/helpdesk/tickets')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Helpdesk/Tickets/Index')
            ->has('tickets')
        );
});

test('authenticated user sees ticket show with ticket prop', function () {
    $user   = User::factory()->create();
    $ticket = Ticket::factory()->create(['reporter_id' => $user->id]);

    $this->actingAs($user)
        ->get("/helpdesk/tickets/{$ticket->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Helpdesk/Tickets/Show')
            ->has('ticket')
        );
});

test('show returns 404 for non-existent ticket', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/helpdesk/tickets/99999')
        ->assertNotFound();
});
