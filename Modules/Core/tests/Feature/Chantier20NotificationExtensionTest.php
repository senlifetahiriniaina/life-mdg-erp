<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\ParticipantNotificationService;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketComment;
use Modules\Validation\Events\ApprovalApproved;
use Modules\Validation\Events\ApprovalRejected;
use Modules\Validation\Events\ApprovalRequestCreated;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;

uses(RefreshDatabase::class);

// Chantier 20 — "toute personne intervenue dans un process, plus la
// hiérarchie supérieure directe du owner de l'action, est notifiée."
// Locks in ParticipantNotificationService itself plus the first wave of
// real wiring (Validation approvals, HR leave requests, Helpdesk tickets/
// comments) against the real notifications table, not just re-read code.

function unreadTitlesFor(User $user): array
{
    return $user->notifications()->get()->map(function ($n) {
        $data = is_string($n->data) ? json_decode($n->data, true) : $n->data;
        return $data['title'] ?? null;
    })->filter()->values()->all();
}

describe('ParticipantNotificationService', function () {
    test('notifies explicit participants plus the owner\'s direct manager, deduplicated', function () {
        $manager = User::factory()->create();
        $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);
        $ownerEmployee = Employee::factory()->create(['manager_id' => $managerEmployee->id]);
        $owner = User::find($ownerEmployee->user_id);
        $participant = User::factory()->create();

        // Callers are responsible for excluding the owner from their own
        // participant list (documented on the service itself) — this
        // exercises the real contract, not an auto-exclusion the service
        // never promised.
        app(ParticipantNotificationService::class)->notifyProcess(
            [$participant],
            $owner,
            'Test process',
            'Un test.',
            ['type' => 'info']
        );

        expect(unreadTitlesFor($participant))->toContain('Test process');
        expect(unreadTitlesFor($manager))->toContain('Test process');
        // Manager is notified exactly once even if resolution could double-count.
        expect($manager->notifications()->count())->toBe(1);
    });

    test('does not double-notify a manager who is also an explicit participant', function () {
        $manager = User::factory()->create();
        $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);
        $ownerEmployee = Employee::factory()->create(['manager_id' => $managerEmployee->id]);
        $owner = User::find($ownerEmployee->user_id);

        app(ParticipantNotificationService::class)->notifyProcess(
            [$manager],
            $owner,
            'Test process',
            'Un test.',
        );

        expect($manager->notifications()->count())->toBe(1);
    });

    test('gracefully no-ops when the owner has no manager', function () {
        $employee = Employee::factory()->create(['manager_id' => null]);
        $owner = User::find($employee->user_id);

        app(ParticipantNotificationService::class)->notifyProcess([], $owner, 'Solo', 'body');

        expect($owner->notifications()->count())->toBe(0);
    });
});

describe('Validation approval notifications', function () {
    test('creating a request notifies the assigned approver and the requester\'s manager', function () {
        // The "owner" of the underlying action here is the requester (their
        // action — e.g. submitting an invoice — is what triggered the
        // request), so it's the requester's manager who is kept informed,
        // not the approver's.
        $managerUser = User::factory()->create();
        $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);
        $requesterEmployee = Employee::factory()->create(['manager_id' => $managerEmployee->id]);
        $requester = User::find($requesterEmployee->user_id);
        $approverEmployee = Employee::factory()->create();
        $approver = User::find($approverEmployee->user_id);

        $workflow = ApprovalWorkflow::factory()->create();
        $request = ApprovalRequest::factory()->create([
            'workflow_id' => $workflow->id,
            'status' => 'pending',
            'requested_by' => $requester->id,
            'approver_id' => $approver->id,
            'current_level' => 1,
            'total_levels' => 1,
        ]);

        event(new ApprovalRequestCreated($request, $workflow));

        expect(unreadTitlesFor($approver))->toContain('Nouvelle demande d\'approbation');
        expect(unreadTitlesFor($managerUser))->toContain('Nouvelle demande d\'approbation');
    });

    test('approving a request notifies the requester', function () {
        $requesterEmployee = Employee::factory()->create();
        $requester = User::find($requesterEmployee->user_id);
        $approverEmployee = Employee::factory()->create();
        $approver = User::find($approverEmployee->user_id);

        $request = ApprovalRequest::factory()->create([
            'status' => 'pending',
            'requested_by' => $requester->id,
            'current_level' => 1,
            'total_levels' => 1,
        ]);

        app(\Modules\Validation\Services\ApprovalRequestService::class)->approveRequest($request, $approver, 'OK');

        expect(unreadTitlesFor($requester))->toContain('Demande approuvée');
    });

    test('rejecting a request notifies the requester', function () {
        $requesterEmployee = Employee::factory()->create();
        $requester = User::find($requesterEmployee->user_id);
        $approverEmployee = Employee::factory()->create();
        $approver = User::find($approverEmployee->user_id);

        $request = ApprovalRequest::factory()->create([
            'status' => 'pending',
            'requested_by' => $requester->id,
        ]);

        app(\Modules\Validation\Services\ApprovalRequestService::class)->rejectRequest($request, $approver, 'Incomplet');

        expect(unreadTitlesFor($requester))->toContain('Demande rejetée');
    });
});

describe('HR leave request notifications', function () {
    test('a new leave request notifies the requester\'s direct manager', function () {
        $managerUser = User::factory()->create();
        $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);
        $employee = Employee::factory()->create(['manager_id' => $managerEmployee->id]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => 'vacation',
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(7),
            'days_requested' => 2,
            'status' => 'pending',
        ]);

        expect(unreadTitlesFor($managerUser))->toContain('Nouvelle demande de congé');
    });

    test('approving a leave request notifies the requesting employee', function () {
        $employee = Employee::factory()->create();
        $approverEmployee = Employee::factory()->create();

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => 'vacation',
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(7),
            'days_requested' => 2,
            'status' => 'pending',
        ]);

        $leave->update(['status' => 'approved', 'approved_by' => $approverEmployee->id]);

        $employeeUser = User::find($employee->user_id);
        expect(unreadTitlesFor($employeeUser))->toContain('Congé approuvé');
    });
});

describe('Helpdesk ticket + comment notifications', function () {
    test('assigning a new ticket notifies the assignee exactly once (no nested-save double-fire)', function () {
        $reporter = User::factory()->create();
        $assignee = User::factory()->create();

        Ticket::factory()->create([
            'reporter_id' => $reporter->id,
            'assignee_id' => $assignee->id,
        ]);

        $titles = unreadTitlesFor($assignee);
        expect(array_count_values($titles)['Nouveau ticket assigné'] ?? 0)->toBe(1);
        // The spurious nested-save "Ticket réassigné" bug must not fire on create.
        expect($titles)->not->toContain('Ticket réassigné');
    });

    test('changing a ticket\'s status notifies reporter and assignee, excluding the acting agent', function () {
        $reporter = User::factory()->create();
        $assignee = User::factory()->create();
        $agent = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'reporter_id' => $reporter->id,
            'assignee_id' => $assignee->id,
            'status' => 'open',
        ]);

        test()->actingAs($agent, 'sanctum');
        $ticket->update(['status' => 'resolved']);

        expect(unreadTitlesFor($reporter))->toContain('Statut du ticket mis à jour');
        expect(unreadTitlesFor($assignee))->toContain('Statut du ticket mis à jour');
        expect(unreadTitlesFor($agent))->not->toContain('Statut du ticket mis à jour');
    });

    test('a new comment notifies the reporter, assignee, and prior commenters — not the author', function () {
        $reporter = User::factory()->create();
        $assignee = User::factory()->create();
        $firstCommenter = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'reporter_id' => $reporter->id,
            'assignee_id' => $assignee->id,
        ]);

        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $firstCommenter->id]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $assignee->id]);

        expect(unreadTitlesFor($reporter))->toContain('Nouveau commentaire sur un ticket');
        expect(unreadTitlesFor($firstCommenter))->toContain('Nouveau commentaire sur un ticket');
        // The assignee authored the 2nd comment — not notified about their own comment.
        $assigneeTitles = unreadTitlesFor($assignee);
        expect(array_count_values($assigneeTitles)['Nouveau commentaire sur un ticket'] ?? 0)->toBe(1);
    });
});

// Chantier 31 — found empirically while re-auditing the invoice approval
// chain: NotificationService::sendToUser() used to json_encode() the
// payload by hand before assigning it to the model's 'data' attribute,
// which DatabaseNotification::$casts already declares as an 'array' cast —
// so Eloquent's own outbound json_encode() wrapped the already-encoded
// string a second time on every real write. The `is_string()` double-decode
// in unreadTitlesFor()/NotificationBell.vue masked this from ever
// surfacing as a visible bug, but the raw DB column — and therefore any
// consumer that doesn't defensively double-decode — was corrupted.
test('sendToUser() stores data single-encoded, not double-encoded', function () {
    $user = User::factory()->create();

    app(App\Services\NotificationService::class)
        ->sendToUser($user, 'Titre', 'Corps', ['type' => 'info', 'foo' => 'bar']);

    $raw = \Illuminate\Support\Facades\DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->value('data');

    // A single json_encode() of the payload decodes straight to an array —
    // if it were double-encoded, decoding once would yield a string, not
    // an array (which is exactly what the pre-fix bug produced).
    expect(json_decode($raw, true))->toBeArray()
        ->toMatchArray(['title' => 'Titre', 'body' => 'Corps']);

    $notification = $user->notifications()->first();
    expect($notification->data)->toBeArray()
        ->toMatchArray(['title' => 'Titre', 'body' => 'Corps']);
});
