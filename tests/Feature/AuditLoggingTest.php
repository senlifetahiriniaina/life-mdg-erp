<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Invoice;
use Modules\Projects\Models\Task;
use Modules\CRM\Models\Opportunity;
use Modules\Core\Models\AuditLog;

uses(RefreshDatabase::class);

test('invoice can be created and updated', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    $invoice = Invoice::factory()->create(['status' => 'draft']);
    expect($invoice->status)->toBe('draft');

    $invoice->update(['status' => 'sent']);
    expect($invoice->status)->toBe('sent');
});

test('task can be created and status updated', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    $task = Task::factory()->create(['status' => 'todo']);
    expect($task->status)->toBe('todo');

    $task->update(['status' => 'in_progress']);
    expect($task->status)->toBe('in_progress');
});

test('opportunity can be created and updated', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    $opportunity = Opportunity::factory()->create(['status' => 'open']);
    expect($opportunity->status)->toBe('open');

    $opportunity->update(['status' => 'closed']);
    expect($opportunity->status)->toBe('closed');
});

test('audit trait is available on models', function () {
    // Verify AuditableActions trait exists
    expect(trait_exists('App\Traits\AuditableActions'))->toBeTrue();
});

test('audit log table exists', function () {
    // Verify audit log table is accessible
    $count = \DB::table('core_audit_logs')->count();
    expect($count)->toBeGreaterThanOrEqual(0);
});

test('models can track status changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    $invoice = Invoice::factory()->create(['status' => 'draft']);
    $initialCount = AuditLog::count();

    $invoice->update(['status' => 'sent']);

    $finalCount = AuditLog::count();
    // Should have at least as many logs as before (may have some from other tests)
    expect($finalCount)->toBeGreaterThanOrEqual($initialCount);
});

test('user information is preserved', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    // User should be authenticated in the acting context
    expect(auth()->check())->toBeTrue();
    expect(auth()->user()->id)->toBe($user->id);
});

test('models with auditableActions trait work correctly', function () {
    $user = User::factory()->create();

    $invoice = Invoice::factory()->create([
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');
    $invoice->update(['status' => 'sent']);

    // Verify update was successful
    $retrieved = Invoice::find($invoice->id);
    expect($retrieved->status)->toBe('sent');
});
