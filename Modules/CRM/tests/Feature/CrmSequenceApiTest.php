<?php

declare(strict_types=1);

use App\Models\User;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\WebForm;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    // Chantier 32.15: EmailSequencePolicy's real create()-ability role requirement is now
    // actually enforced (see EmailSequenceController/CRMServiceProvider) — a bare, roleless
    // user 403s on every mutating action. Same fix pattern as EmailSequenceTest.php.
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->user->assignRole('admin');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('authenticated user can list email sequences', function () {
    EmailSequence::factory()->count(3)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/crm/sequences')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('authenticated user can create a sequence with steps', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/crm/sequences', [
            'name' => 'Welcome sequence',
            'trigger' => 'manual',
            'steps' => [
                ['step_order' => 1, 'delay_hours' => 0, 'subject' => 'Welcome!', 'body_html' => '<p>Hi</p>'],
                ['step_order' => 2, 'delay_hours' => 24, 'subject' => 'Follow-up', 'body_html' => '<p>Checking in</p>'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Welcome sequence')
        ->assertJsonCount(2, 'steps');
});

it('authenticated user can enroll a lead in a sequence', function () {
    $sequence = EmailSequence::factory()->create();
    $lead = Lead::factory()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/crm/sequences/{$sequence->id}/enroll", ['lead_id' => $lead->id])
        ->assertCreated()
        ->assertJsonPath('sequence_id', $sequence->id);
});

it('public web form submission creates a lead', function () {
    $form = WebForm::factory()->create([
        'slug' => 'contact-us',
        'create_lead' => true,
        'is_active' => true,
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ],
    ]);

    $this->postJson('/api/v1/crm/forms/contact-us/submit', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ])->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('crm_leads', ['title' => 'John Doe']);
});

it('unauthenticated user cannot list sequences', function () {
    $this->getJson('/api/v1/crm/sequences')->assertUnauthorized();
});
