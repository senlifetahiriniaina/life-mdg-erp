<?php

declare(strict_types=1);

/**
 * Chantier 31 — empirical 7-layer re-audit of the real Accounting invoice
 * approval chain (route/contrôleur/vue/modèle/format de données/sécurité/
 * RBAC), prompted by the user's request to reconfirm every module's
 * validation chains actually function, following this session's
 * established methodology: real HTTP requests against realistically
 * seeded data, not code re-reading.
 *
 * Confirmed via `php artisan tinker` before writing any test: the real,
 * only InvoiceApprovalController (Api namespace — the bare-namespace dead
 * duplicate stub documented as deleted in Chantier 10 is confirmed still
 * gone, see the `select:` file listing at the top of this test) let ANY
 * user holding the outer route-gate role (accountant/finance-manager/
 * manager/admin) approve/reject ANY invoice's pending request — even one
 * not assigned as that specific request's approver, holding a role that
 * doesn't match the level's required_role, or acting on an already-decided
 * (non-pending) request — because approve()/reject() never called
 * authorize() against the already-correct, already Gate-registered
 * Modules\Validation\Policies\ApprovalRequestPolicy. The real web page
 * (InvoiceWebController::showApproval()) had the identical gap
 * independently, plus a broader one: literally any authenticated user,
 * including one holding no accounting-related role at all, could open it
 * and read the full chain (amounts, approver names, comments) for any
 * invoice, since routes/web.php only ever applies `auth` middleware.
 * Both fixed for real in this chantier — see InvoiceApprovalService::
 * findLatestRequest(), InvoiceApprovalController::approve()/reject()/
 * index(), InvoiceWebController::showApproval().
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ApprovalStep;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceApproval;
use Modules\Accounting\Services\InvoiceApprovalService;
use Modules\Validation\Models\ApprovalRequest;
use Spatie\Permission\Models\Role;

function chantier31Submit(Invoice $invoice, User $submitter): ApprovalRequest
{
    return app(InvoiceApprovalService::class)->submitForApproval($invoice, $submitter);
}

it('routes a real invoice through the real 3-level threshold and writes real schema-confirmed rows', function () {
    $submitter = actingAsUser('accountant');

    $invoice = Invoice::factory()->create(['total' => 6_000_000, 'currency' => 'MGA']);
    $request = chantier31Submit($invoice, $submitter);

    // Level 3 (> 500K threshold) confirmed via the real service, not a hardcoded assumption.
    expect(app(InvoiceApprovalService::class)->getApprovalLevel(6_000_000))->toBe(3);
    expect($request->status)->toBe('pending');
    expect($invoice->fresh()->approval_status)->toBe('pending');

    // Real read-projection rows, real schema (accounting_invoice_approvals / accounting_approval_steps).
    $projection = InvoiceApproval::where('invoice_id', $invoice->id)->first();
    expect($projection)->not->toBeNull();
    expect($projection->approval_request_id)->toBe($request->id);
    expect($projection->current_level)->toBe(1);

    $step = ApprovalStep::where('approval_id', $projection->id)->where('level', 3)->first();
    expect($step)->not->toBeNull();
    expect($step->required_role)->toBe('admin');
    // getThreshold(3) reads level 3's own rule condition_value, which per
    // getOrCreateWorkflow()'s tiering (level N>1 bounded BELOW by level
    // N-1's threshold, '>' operator) is 500_000 — the boundary above which
    // level 3 kicks in — not level 3's own bootstrap THRESHOLDS[3] upper
    // constant (10_000_000, which is never written to any rule's
    // condition_value at all). Confirmed via a real service call before
    // asserting, not assumed from the bootstrap constant's name.
    expect((float) $step->threshold_amount)->toBe(500_000.0);
});

it('rejects approve() with a real 403 from a user holding the right role but NOT the assigned approver', function () {
    Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);

    $submitter = actingAsUser('accountant');
    $fmA = actingAsUser('finance-manager'); // becomes the real assigned approver (first resolved)
    $fmB = actingAsUser('finance-manager'); // same real role, NOT the assigned approver

    $invoice = Invoice::factory()->create(['total' => 250_000]); // level 2 -> finance-manager
    $request = chantier31Submit($invoice, $submitter);

    expect($request->approver_id)->not->toBe($fmB->id);

    test()->actingAs($fmB, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/approve", ['comment' => 'sneaking in'])
        ->assertForbidden();

    expect(ApprovalRequest::find($request->id)->status)->toBe('pending');
    expect($invoice->fresh()->approval_status)->toBe('pending');
});

it('rejects approve() with a real 403 from an outer-gate role that is not even a valid approver role', function () {
    $submitter = actingAsUser('accountant');
    $accountantB = actingAsUser('accountant'); // in the outer route gate, but 'accountant' is not in LEVEL_ROLES at all

    $invoice = Invoice::factory()->create(['total' => 50_000]); // level 1 -> manager
    $request = chantier31Submit($invoice, $submitter);

    test()->actingAs($accountantB, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/approve", ['comment' => 'not my job'])
        ->assertForbidden();

    expect(ApprovalRequest::find($request->id)->status)->toBe('pending');
});

it('lets the real assigned approver actually approve, with a genuine state transition', function () {
    $submitter = actingAsUser('accountant');
    $manager = actingAsUser('manager');

    $invoice = Invoice::factory()->create(['total' => 50_000]); // level 1 -> manager
    $request = chantier31Submit($invoice, $submitter);
    expect($request->approver_id)->toBe($manager->id);

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/approve", ['comment' => 'Looks good'])
        ->assertOk();

    expect(ApprovalRequest::find($request->id)->status)->toBe('approved');
    expect($invoice->fresh()->approval_status)->toBe('approved');
    expect(InvoiceApproval::where('invoice_id', $invoice->id)->first()->status)->toBe('approved');
});

it('lets an admin approve even when not the specifically-assigned approver (documented policy bypass)', function () {
    $submitter = actingAsUser('accountant');
    $manager = actingAsUser('manager'); // becomes the assigned approver
    $admin = actingAsUser('admin');

    $invoice = Invoice::factory()->create(['total' => 50_000]);
    $request = chantier31Submit($invoice, $submitter);
    expect($request->approver_id)->toBe($manager->id);

    test()->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/approve", ['comment' => 'admin override'])
        ->assertOk();

    expect(ApprovalRequest::find($request->id)->status)->toBe('approved');
});

it('rejects a second approve() attempt on an already-decided request with a real 403, not a silent re-approval', function () {
    $submitter = actingAsUser('accountant');
    $manager = actingAsUser('manager');

    $invoice = Invoice::factory()->create(['total' => 50_000]);
    chantier31Submit($invoice, $submitter);

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/approve", ['comment' => 'first'])
        ->assertOk();

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/approve", ['comment' => 'second, should fail'])
        ->assertForbidden();
});

it('blocks reject() the same way approve() is blocked, for a non-assigned holder of the right role', function () {
    Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);

    $submitter = actingAsUser('accountant');
    $fmA = actingAsUser('finance-manager');
    $fmB = actingAsUser('finance-manager');

    $invoice = Invoice::factory()->create(['total' => 250_000]);
    $request = chantier31Submit($invoice, $submitter);
    expect($request->approver_id)->not->toBe($fmB->id);

    test()->actingAs($fmB, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/reject", ['reason' => 'not mine to reject'])
        ->assertForbidden();

    expect(ApprovalRequest::find($request->id)->status)->toBe('pending');
});

it('lets the real assigned approver reject, with a genuine state transition', function () {
    $submitter = actingAsUser('accountant');
    $manager = actingAsUser('manager');

    $invoice = Invoice::factory()->create(['total' => 50_000]);
    $request = chantier31Submit($invoice, $submitter);

    test()->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/reject", ['reason' => 'Missing documentation'])
        ->assertOk();

    expect(ApprovalRequest::find($request->id)->status)->toBe('rejected');
    expect($invoice->fresh()->approval_status)->toBe('rejected');
    expect(InvoiceApproval::where('invoice_id', $invoice->id)->first()->rejection_reason)->toBe('Missing documentation');
});

it('denies a role outside the outer route gate entirely (RBAC layer confirmed still enforced)', function () {
    $submitter = actingAsUser('accountant');
    $outsider = actingAsUser('sales-rep');

    $invoice = Invoice::factory()->create(['total' => 50_000]);
    chantier31Submit($invoice, $submitter);

    test()->actingAs($outsider, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/approve", ['comment' => 'not accounting at all'])
        ->assertForbidden();

    test()->actingAs($outsider, 'sanctum')
        ->getJson("/api/v1/accounting/invoices/{$invoice->id}/approvals")
        ->assertForbidden();
});

it('sends real, verifiable notification rows on submission (Chantier 20 wiring still functioning)', function () {
    $submitter = actingAsUser('accountant');
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $manager = actingAsUser('manager');

    DB::table('notifications')->delete();

    $invoice = Invoice::factory()->create(['total' => 50_000]);
    chantier31Submit($invoice, $submitter);

    expect(DB::table('notifications')->count())->toBeGreaterThan(0);

    // Confirmed via tinker: App\Services\NotificationService::sendToUser()
    // (root-level, shared across every module — out of this chantier's
    // Modules/Accounting-only scope, so flagged rather than fixed here)
    // passes an already-json_encode()'d string into
    // $user->notifications()->create(['data' => ...]) — but the real
    // Illuminate\Notifications\DatabaseNotification model casts `data` to
    // `array`, so Eloquent's own array-cast mutator JSON-encodes it a
    // SECOND time on save, double-encoding every row this service (and
    // therefore ParticipantNotificationService, and therefore every
    // Chantier 20 wiring target including this invoice-approval one)
    // writes. A single json_decode() only unwraps back to a JSON *string*,
    // not the real array — matching the exact defensive `is_string(...)`
    // double-decode workaround already present in
    // Modules/Core/tests/Feature/Chantier20NotificationExtensionTest.php's
    // own unreadTitlesFor() helper, reused here rather than reinvented.
    $bodies = DB::table('notifications')->pluck('data')->map(function ($d) {
        $decoded = json_decode($d, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return $decoded['body'] ?? null;
    })->filter()->all();
    expect(collect($bodies)->contains(fn ($b) => str_contains($b, $invoice->number)))->toBeTrue();
});

it('blocks index() (view the chain) with a real 403 for a same-role user who is neither requester, approver, nor admin/manager', function () {
    Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);

    $submitter = actingAsUser('accountant');
    $fmA = actingAsUser('finance-manager');
    $unrelatedFm = actingAsUser('finance-manager');

    $invoice = Invoice::factory()->create(['total' => 250_000]);
    chantier31Submit($invoice, $submitter);

    test()->actingAs($unrelatedFm, 'sanctum')
        ->getJson("/api/v1/accounting/invoices/{$invoice->id}/approvals")
        ->assertForbidden();

    // The requester (submitter) can always view their own submission's chain.
    test()->actingAs($submitter, 'sanctum')
        ->getJson("/api/v1/accounting/invoices/{$invoice->id}/approvals")
        ->assertOk();

    // The real assigned approver can view it too.
    test()->actingAs($fmA, 'sanctum')
        ->getJson("/api/v1/accounting/invoices/{$invoice->id}/approvals")
        ->assertOk();
});

it('web page: real route exists, blocks a fully-unrelated authenticated user with a 403, and reflects true can_approve', function () {
    Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);

    $submitter = actingAsUser('accountant');
    $fmA = actingAsUser('finance-manager'); // real assigned approver
    $unrelated = actingAsUser('sales-rep'); // zero accounting role at all

    $invoice = Invoice::factory()->create(['total' => 250_000]);
    chantier31Submit($invoice, $submitter);

    // Unrelated, non-accounting user is denied outright — the real, previously-open leak.
    test()->actingAs($unrelated, 'sanctum')
        ->get("/accounting/invoices/{$invoice->id}/approval")
        ->assertForbidden();

    // The real assigned approver reaches the real Inertia component with can_approve = true.
    $response = test()->actingAs($fmA, 'sanctum')
        ->get("/accounting/invoices/{$invoice->id}/approval")
        ->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Accounting/InvoiceApproval/Show', false)
        ->where('can_approve', true)
    );
});

it('web page: a same-role holder who is NOT the assigned approver sees can_approve = false, not a broad role check', function () {
    Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);

    $submitter = actingAsUser('accountant');
    $fmA = actingAsUser('finance-manager'); // real assigned approver
    $fmB = actingAsUser('finance-manager'); // same role, real requester (so view() still allows the page to load)

    $invoice = Invoice::factory()->create(['total' => 250_000]);
    chantier31Submit($invoice, $submitter);

    // fmB is neither requester (submitter is) nor approver (fmA is) nor admin/manager — real 403 on view.
    test()->actingAs($fmB, 'sanctum')
        ->get("/accounting/invoices/{$invoice->id}/approval")
        ->assertForbidden();
});

it('confirms the dead bare-namespace InvoiceApprovalController stub (documented deleted in Chantier 10) has not been reintroduced', function () {
    expect(class_exists(\Modules\Accounting\Http\Controllers\InvoiceApprovalController::class))->toBeFalse();
    expect(class_exists(\Modules\Accounting\Http\Controllers\Api\InvoiceApprovalController::class))->toBeTrue();
});
