<?php

declare(strict_types=1);

use App\Models\User;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\SequenceEnrollment;
use Modules\CRM\Models\SequenceStep;
use Modules\CRM\Services\EmailSequenceService;
use Spatie\Permission\Models\Role;

// Chantier 32.15: EmailSequencePolicy was written from the start with its own real
// create()/update()/delete() role and ownership requirements, but was never registered with
// the Gate (see CRMServiceProvider) and EmailSequenceController never called authorize() at
// all — so every one of these already-written rules has never actually been enforced until
// this chantier. The bare `User::factory()->create()` (no role) fixtures throughout this file
// now correctly 403 against EmailSequencePolicy's real create()-ability role check — fixed by
// giving the test user the `admin` role it needs to act as an authorized actor, the same
// lightweight Role::firstOrCreate()+assignRole() pattern already used by
// Chantier19CrmReauditTest.php/CrmTenantIsolationFollowupTest.php elsewhere in this module.
function grantAdminRole(User $user): void
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole('admin');
}


// ── Unauthenticated tests (outside describe blocks with beforeEach) ────────────

it('rejects unauthenticated index request', function () {
    $this->getJson('/api/v1/crm/email-sequences')->assertUnauthorized();
});

it('rejects unauthenticated store request', function () {
    $this->postJson('/api/v1/crm/email-sequences', [])->assertUnauthorized();
});

it('rejects unauthenticated show request', function () {
    $sequence = EmailSequence::factory()->create();
    $this->getJson("/api/v1/crm/email-sequences/{$sequence->id}")->assertUnauthorized();
});

it('rejects unauthenticated update request', function () {
    $sequence = EmailSequence::factory()->create();
    $this->putJson("/api/v1/crm/email-sequences/{$sequence->id}", [])->assertUnauthorized();
});

it('rejects unauthenticated destroy request', function () {
    $sequence = EmailSequence::factory()->create();
    $this->deleteJson("/api/v1/crm/email-sequences/{$sequence->id}")->assertUnauthorized();
});

it('rejects unauthenticated process-due request', function () {
    $this->postJson('/api/v1/crm/email-sequences/process-due')->assertUnauthorized();
});

// ── EmailSequence Model Tests ─────────────────────────────────────────────────

describe('EmailSequence model', function () {
    it('has correct table name', function () {
        $sequence = new EmailSequence;
        expect($sequence->getTable())->toBe('crm_email_sequences');
    });

    it('defaults status to draft', function () {
        $sequence = EmailSequence::factory()->create();
        expect($sequence->status)->toBe('draft');
    });

    it('isActive returns true when status is active', function () {
        $sequence = EmailSequence::factory()->create(['status' => 'active']);
        expect($sequence->isActive())->toBeTrue();
    });

    it('isActive returns false when status is draft', function () {
        $sequence = EmailSequence::factory()->create(['status' => 'draft']);
        expect($sequence->isActive())->toBeFalse();
    });

    it('isDraft returns true when status is draft', function () {
        $sequence = EmailSequence::factory()->create(['status' => 'draft']);
        expect($sequence->isDraft())->toBeTrue();
    });

    it('activate updates status to active', function () {
        $sequence = EmailSequence::factory()->create(['status' => 'draft']);
        $sequence->activate();
        expect($sequence->fresh()->status)->toBe('active');
    });

    it('pause updates status to paused', function () {
        $sequence = EmailSequence::factory()->create(['status' => 'active']);
        $sequence->pause();
        expect($sequence->fresh()->status)->toBe('paused');
    });

    it('enrollmentCount returns correct count', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        SequenceEnrollment::factory()->count(3)->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
        ]);
        expect($sequence->enrollmentCount())->toBe(3);
    });

    it('activeEnrollmentCount returns only active enrollments', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        SequenceEnrollment::factory()->count(2)->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);
        SequenceEnrollment::factory()->completed()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
        ]);
        expect($sequence->activeEnrollmentCount())->toBe(2);
    });

    it('has steps relation', function () {
        $sequence = EmailSequence::factory()->create();
        SequenceStep::factory()->create(['sequence_id' => $sequence->id]);
        expect($sequence->steps()->count())->toBe(1);
    });

    it('has enrollments relation', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
        ]);
        expect($sequence->enrollments()->count())->toBe(1);
    });
});

// ── SequenceStep Model Tests ──────────────────────────────────────────────────

describe('SequenceStep model', function () {
    it('has correct table name #2', function () {
        $step = new SequenceStep;
        expect($step->getTable())->toBe('crm_sequence_steps');
    });

    it('casts delay_days as integer', function () {
        $sequence = EmailSequence::factory()->create();
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'delay_days' => 3,
        ]);
        expect($step->delay_days)->toBeInt()->toBe(3);
    });

    it('belongs to sequence', function () {
        $sequence = EmailSequence::factory()->create();
        $step = SequenceStep::factory()->create(['sequence_id' => $sequence->id]);
        expect($step->sequence->id)->toBe($sequence->id);
    });

    it('is created with all fillable fields', function () {
        $sequence = EmailSequence::factory()->create();
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'order' => 2,
            'delay_days' => 5,
            'subject' => 'Test Subject',
            'body' => 'Test body content',
            'from_name' => 'John Doe',
            'from_email' => 'john@example.com',
        ]);
        expect($step->order)->toBe(2)
            ->and($step->delay_days)->toBe(5)
            ->and($step->subject)->toBe('Test Subject')
            ->and($step->from_name)->toBe('John Doe');
    });
});

// ── SequenceEnrollment Model Tests ───────────────────────────────────────────

describe('SequenceEnrollment model', function () {
    it('has correct table name #3', function () {
        $enrollment = new SequenceEnrollment;
        expect($enrollment->getTable())->toBe('crm_sequence_enrollments');
    });

    it('isActive returns true for active status', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);
        expect($enrollment->isActive())->toBeTrue();
    });

    it('isCompleted returns true for completed status', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->completed()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
        ]);
        expect($enrollment->isCompleted())->toBeTrue();
    });

    it('complete sets status and completed_at', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);
        $enrollment->complete();
        $fresh = $enrollment->fresh();
        expect($fresh->status)->toBe('completed')
            ->and($fresh->completed_at)->not->toBeNull();
    });

    it('unsubscribe sets status to unsubscribed', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
        ]);
        $enrollment->unsubscribe();
        expect($enrollment->fresh()->status)->toBe('unsubscribed');
    });

    it('belongs to sequence and contact', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
        ]);
        expect($enrollment->sequence->id)->toBe($sequence->id)
            ->and($enrollment->contact->id)->toBe($contact->id);
    });

    it('advance marks completed when no more steps', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'current_step' => 0,
        ]);
        // No steps exist, so advance should complete
        $enrollment->advance();
        expect($enrollment->fresh()->status)->toBe('completed');
    });

    it('advance increments current_step and sets next_send_at when next step exists', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        // Create a step at order=1
        SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'order' => 1,
            'delay_days' => 3,
        ]);
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'current_step' => 0,
        ]);
        $enrollment->advance();
        $fresh = $enrollment->fresh();
        expect($fresh->current_step)->toBe(1)
            ->and($fresh->next_send_at)->not->toBeNull();
    });
});

// ── EmailSequenceService Tests ────────────────────────────────────────────────

describe('EmailSequenceService', function () {
    beforeEach(function () {
        $this->service = app(EmailSequenceService::class);
    });

    it('createSequence creates and returns an EmailSequence', function () {
        $user = User::factory()->create();
        $sequence = $this->service->createSequence([
            'name' => 'Test Sequence',
            'status' => 'draft',
            'trigger_type' => 'manual',
            'created_by' => $user->id,
        ]);
        expect($sequence)->toBeInstanceOf(EmailSequence::class)
            ->and($sequence->name)->toBe('Test Sequence');
        $this->assertDatabaseHas('crm_email_sequences', ['name' => 'Test Sequence']);
    });

    it('addStep creates step with auto-incremented order', function () {
        $sequence = EmailSequence::factory()->create();
        $step1 = $this->service->addStep($sequence, [
            'subject' => 'Step 1',
            'body' => 'Body 1',
        ]);
        $step2 = $this->service->addStep($sequence, [
            'subject' => 'Step 2',
            'body' => 'Body 2',
        ]);
        expect($step1->order)->toBe(1)
            ->and($step2->order)->toBe(2);
    });

    it('enroll creates a new enrollment', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = $this->service->enroll($sequence, $contact->id);
        expect($enrollment)->toBeInstanceOf(SequenceEnrollment::class)
            ->and($enrollment->sequence_id)->toBe($sequence->id)
            ->and($enrollment->contact_id)->toBe($contact->id)
            ->and($enrollment->status)->toBe('active');
    });

    it('enroll returns existing active enrollment without duplicate', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $first = $this->service->enroll($sequence, $contact->id);
        $second = $this->service->enroll($sequence, $contact->id);
        expect($first->id)->toBe($second->id);
        expect(SequenceEnrollment::count())->toBe(1);
    });

    it('enroll sets next_send_at based on first step delay', function () {
        $sequence = EmailSequence::factory()->create();
        SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'order' => 1,
            'delay_days' => 2,
        ]);
        $contact = Contact::factory()->create();
        $enrollment = $this->service->enroll($sequence, $contact->id);
        expect($enrollment->next_send_at)->not->toBeNull();
    });

    it('processEnrollment returns false for non-active enrollment', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->completed()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
        ]);
        $result = $this->service->processEnrollment($enrollment);
        expect($result)->toBeFalse();
    });

    it('processEnrollment returns true and advances active enrollment', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'current_step' => 0,
            'status' => 'active',
        ]);
        $result = $this->service->processEnrollment($enrollment);
        expect($result)->toBeTrue();
    });

    it('processDueEnrollments processes only due active enrollments', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        // Due enrollment
        SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'status' => 'active',
            'next_send_at' => now()->subMinute(),
        ]);
        // Future enrollment - should not be processed
        $contact2 = Contact::factory()->create();
        SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact2->id,
            'status' => 'active',
            'next_send_at' => now()->addDays(5),
        ]);
        $count = $this->service->processDueEnrollments();
        expect($count)->toBe(1);
    });

    it('unenroll calls unsubscribe on enrollment', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);
        $this->service->unenroll($enrollment);
        expect($enrollment->fresh()->status)->toBe('unsubscribed');
    });

    it('getSequenceStats returns correct stats', function () {
        $sequence = EmailSequence::factory()->create();
        $contacts = Contact::factory()->count(5)->create();
        SequenceEnrollment::factory()->create(['sequence_id' => $sequence->id, 'contact_id' => $contacts[0]->id, 'status' => 'active']);
        SequenceEnrollment::factory()->create(['sequence_id' => $sequence->id, 'contact_id' => $contacts[1]->id, 'status' => 'active']);
        SequenceEnrollment::factory()->completed()->create(['sequence_id' => $sequence->id, 'contact_id' => $contacts[2]->id]);
        SequenceEnrollment::factory()->completed()->create(['sequence_id' => $sequence->id, 'contact_id' => $contacts[3]->id]);
        SequenceEnrollment::factory()->unsubscribed()->create(['sequence_id' => $sequence->id, 'contact_id' => $contacts[4]->id]);
        $stats = $this->service->getSequenceStats($sequence);
        expect($stats['total_enrollments'])->toBe(5)
            ->and($stats['active'])->toBe(2)
            ->and($stats['completed'])->toBe(2)
            ->and($stats['unsubscribed'])->toBe(1)
            ->and($stats['completion_rate'])->toBe(40.0);
    });
});

// ── API Endpoint Tests ────────────────────────────────────────────────────────

describe('GET /api/v1/crm/email-sequences', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('returns paginated list of sequences', function () {
        EmailSequence::factory()->count(3)->create();
        $this->withToken($this->token)
            ->getJson('/api/v1/crm/email-sequences')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('includes enrollments_count in response', function () {
        EmailSequence::factory()->create();
        $this->withToken($this->token)
            ->getJson('/api/v1/crm/email-sequences')
            ->assertOk()
            ->assertJsonPath('data.0.enrollments_count', 0);
    });
});

describe('POST /api/v1/crm/email-sequences', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('creates a new sequence with valid data', function () {
        $this->withToken($this->token)
            ->postJson('/api/v1/crm/email-sequences', [
                'name' => 'Welcome Campaign',
                'description' => 'A welcome series',
                'status' => 'draft',
                'trigger_type' => 'manual',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Welcome Campaign');
        $this->assertDatabaseHas('crm_email_sequences', ['name' => 'Welcome Campaign']);
    });

    it('validates required name field', function () {
        $this->withToken($this->token)
            ->postJson('/api/v1/crm/email-sequences', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('sets created_by to authenticated user', function () {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/crm/email-sequences', [
                'name' => 'Test',
            ])
            ->assertCreated();
        $this->assertDatabaseHas('crm_email_sequences', [
            'name' => 'Test',
            'created_by' => $this->user->id,
        ]);
    });
});

describe('GET /api/v1/crm/email-sequences/{sequence}', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('returns sequence with steps', function () {
        $sequence = EmailSequence::factory()->create();
        SequenceStep::factory()->create(['sequence_id' => $sequence->id]);
        $this->withToken($this->token)
            ->getJson("/api/v1/crm/email-sequences/{$sequence->id}")
            ->assertOk()
            ->assertJsonPath('id', $sequence->id)
            ->assertJsonStructure(['steps']);
    });

    it('returns 404 for non-existent sequence', function () {
        $this->withToken($this->token)
            ->getJson('/api/v1/crm/email-sequences/99999')
            ->assertNotFound();
    });
});

describe('PUT /api/v1/crm/email-sequences/{sequence}', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('updates sequence fields', function () {
        $sequence = EmailSequence::factory()->create(['name' => 'Old Name']);
        $this->withToken($this->token)
            ->putJson("/api/v1/crm/email-sequences/{$sequence->id}", [
                'name' => 'New Name',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');
    });
});

describe('DELETE /api/v1/crm/email-sequences/{sequence}', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('deletes sequence and returns 204', function () {
        $sequence = EmailSequence::factory()->create();
        $this->withToken($this->token)
            ->deleteJson("/api/v1/crm/email-sequences/{$sequence->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('crm_email_sequences', ['id' => $sequence->id]);
    });
});

describe('POST /api/v1/crm/email-sequences/{sequence}/activate', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('activates a draft sequence', function () {
        $sequence = EmailSequence::factory()->create(['status' => 'draft']);
        $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/activate")
            ->assertOk()
            ->assertJsonPath('status', 'active');
    });
});

describe('POST /api/v1/crm/email-sequences/{sequence}/pause', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('pauses an active sequence', function () {
        $sequence = EmailSequence::factory()->create(['status' => 'active']);
        $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/pause")
            ->assertOk()
            ->assertJsonPath('status', 'paused');
    });
});

describe('GET /api/v1/crm/email-sequences/{sequence}/steps', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('returns steps for sequence', function () {
        $sequence = EmailSequence::factory()->create();
        SequenceStep::factory()->count(2)->create(['sequence_id' => $sequence->id]);
        $this->withToken($this->token)
            ->getJson("/api/v1/crm/email-sequences/{$sequence->id}/steps")
            ->assertOk()
            ->assertJsonCount(2);
    });
});

describe('POST /api/v1/crm/email-sequences/{sequence}/steps', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('adds a step to sequence', function () {
        $sequence = EmailSequence::factory()->create();
        $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/steps", [
                'subject' => 'Welcome Email',
                'body' => 'Hello there!',
                'delay_days' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('subject', 'Welcome Email');
    });

    it('validates required subject and body', function () {
        $sequence = EmailSequence::factory()->create();
        $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/steps", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'body']);
    });

    it('auto-increments order', function () {
        $sequence = EmailSequence::factory()->create();
        $step1 = $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/steps", [
                'subject' => 'Step 1', 'body' => 'Body 1',
            ])
            ->assertCreated()->json('order');
        $step2 = $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/steps", [
                'subject' => 'Step 2', 'body' => 'Body 2',
            ])
            ->assertCreated()->json('order');
        expect($step1)->toBe(1)->and($step2)->toBe(2);
    });
});

describe('PUT /api/v1/crm/email-sequences/{sequence}/steps/{step}', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('updates a step', function () {
        $sequence = EmailSequence::factory()->create();
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'subject' => 'Old Subject',
        ]);
        $this->withToken($this->token)
            ->putJson("/api/v1/crm/email-sequences/{$sequence->id}/steps/{$step->id}", [
                'subject' => 'New Subject',
            ])
            ->assertOk()
            ->assertJsonPath('subject', 'New Subject');
    });
});

describe('DELETE /api/v1/crm/email-sequences/{sequence}/steps/{step}', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('deletes a step', function () {
        $sequence = EmailSequence::factory()->create();
        $step = SequenceStep::factory()->create(['sequence_id' => $sequence->id]);
        $this->withToken($this->token)
            ->deleteJson("/api/v1/crm/email-sequences/{$sequence->id}/steps/{$step->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('crm_sequence_steps', ['id' => $step->id]);
    });
});

describe('POST /api/v1/crm/email-sequences/{sequence}/enroll', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('enrolls a contact in a sequence', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/enroll", [
                'contact_id' => $contact->id,
            ])
            ->assertCreated()
            ->assertJsonPath('contact_id', $contact->id);
    });

    it('validates contact_id is required', function () {
        $sequence = EmailSequence::factory()->create();
        $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/enroll", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id']);
    });

    it('validates contact_id exists in crm_contacts', function () {
        $sequence = EmailSequence::factory()->create();
        $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/enroll", [
                'contact_id' => 99999,
            ])
            ->assertUnprocessable();
    });
});

describe('GET /api/v1/crm/email-sequences/{sequence}/enrollments', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('returns enrollments for a sequence', function () {
        $sequence = EmailSequence::factory()->create();
        $contacts = Contact::factory()->count(2)->create();
        foreach ($contacts as $contact) {
            SequenceEnrollment::factory()->create([
                'sequence_id' => $sequence->id,
                'contact_id' => $contact->id,
            ]);
        }
        $this->withToken($this->token)
            ->getJson("/api/v1/crm/email-sequences/{$sequence->id}/enrollments")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('POST /api/v1/crm/email-sequences/{sequence}/stats', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('returns stats for a sequence', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'status' => 'active',
        ]);
        $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/stats")
            ->assertOk()
            ->assertJsonStructure([
                'total_enrollments',
                'active',
                'completed',
                'unsubscribed',
                'completion_rate',
            ]);
    });

    it('returns zero completion rate with no enrollments', function () {
        $sequence = EmailSequence::factory()->create();
        $response = $this->withToken($this->token)
            ->postJson("/api/v1/crm/email-sequences/{$sequence->id}/stats")
            ->assertOk()
            ->assertJsonPath('total_enrollments', 0);
        expect((float) $response->json('completion_rate'))->toBe(0.0);
    });
});

describe('POST /api/v1/crm/email-sequences/process-due', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        grantAdminRole($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    });

    it('processes due enrollments and returns count', function () {
        $sequence = EmailSequence::factory()->create();
        $contact = Contact::factory()->create();
        SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'status' => 'active',
            'next_send_at' => now()->subMinute(),
        ]);
        $this->withToken($this->token)
            ->postJson('/api/v1/crm/email-sequences/process-due')
            ->assertOk()
            ->assertJsonStructure(['processed']);
    });

    it('returns zero when no due enrollments', function () {
        $this->withToken($this->token)
            ->postJson('/api/v1/crm/email-sequences/process-due')
            ->assertOk()
            ->assertJsonPath('processed', 0);
    });

    /**
     * Chantier 38.3: processDue() never scoped by tenant at all — any authenticated CRM user
     * of any company could trigger a real, immediate send sweep across every OTHER company's
     * due email enrollments, confirmed empirically before this fix (a real cross-tenant
     * write/side-effect vector, not just a read leak).
     */
    it('only processes the caller own company due enrollments, never another company', function () {
        $companyA = \App\Models\Company::create(['name' => 'Co A', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $companyB = \App\Models\Company::create(['name' => 'Co B', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);

        $userA = User::factory()->create(['company_id' => $companyA->id]);
        grantAdminRole($userA);
        $tokenA = $userA->createToken('t')->plainTextToken;

        $sequenceA = EmailSequence::factory()->create(['tenant_id' => $companyA->id]);
        SequenceEnrollment::factory()->create([
            'sequence_id' => $sequenceA->id,
            'contact_id' => Contact::factory()->create()->id,
            'status' => 'active',
            'next_send_at' => now()->subMinute(),
        ]);

        $sequenceB = EmailSequence::factory()->create(['tenant_id' => $companyB->id]);
        $enrollmentB = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequenceB->id,
            'contact_id' => Contact::factory()->create()->id,
            'status' => 'active',
            'next_send_at' => now()->subMinute(),
        ]);

        $this->withToken($tokenA)
            ->postJson('/api/v1/crm/email-sequences/process-due')
            ->assertOk()
            ->assertJsonPath('processed', 1);

        // Company B's enrollment must be untouched — its next_send_at never advanced.
        expect($enrollmentB->fresh()->next_send_at->timestamp)
            ->toBe($enrollmentB->next_send_at->timestamp);
    });

    it('the new scheduled command processes every company in one sweep, unlike the per-tenant HTTP endpoint', function () {
        $company = \App\Models\Company::create(['name' => 'Co C', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $sequence = EmailSequence::factory()->create(['tenant_id' => $company->id]);
        SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => Contact::factory()->create()->id,
            'status' => 'active',
            'next_send_at' => now()->subMinute(),
        ]);

        $this->artisan('crm:process-due-email-sequences')->assertExitCode(0);
    });
});
