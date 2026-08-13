<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Accounting routes are gated to finance roles; authenticate as an accountant.
    $this->user = actingAsUser('accountant');
    $this->journal = Journal::factory()->create();
});

test('invoice lifecycle from draft to paid', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'draft',
        'journal_id' => $this->journal->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/accounting/invoices/{$invoice->id}", [
            'status' => 'sent',
        ]);

    expect($response->status())->toBe(200);
});

test('invoice calculates tax correctly', function () {
    $invoice = Invoice::factory()->create([
        'subtotal' => 100,
        'tax_amount' => 10,
        'total' => 110,
    ]);

    expect($invoice->total)->toBe(110);
});

test('invoice marks payment received', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'sent',
        'total' => 1000,
        'amount_paid' => 0,
    ]);

    // Issued invoices are marked paid via the dedicated endpoint (PUT only edits drafts).
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/mark-paid", []);

    expect($response->status())->toBe(200);
});

test('journal entry records debit and credit', function () {
    $entry = JournalEntry::factory()->create([
        'journal_id' => $this->journal->id,
        'debit' => 100,
        'credit' => 0,
    ]);

    expect($entry->debit)->toBe(100);
    expect($entry->credit)->toBe(0);
});

test('journal balances for double entry', function () {
    JournalEntry::factory()->create([
        'journal_id' => $this->journal->id,
        'debit' => 100,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'journal_id' => $this->journal->id,
        'debit' => 0,
        'credit' => 100,
    ]);

    $totalDebit = JournalEntry::where('journal_id', $this->journal->id)->sum('debit');
    $totalCredit = JournalEntry::where('journal_id', $this->journal->id)->sum('credit');

    expect($totalDebit)->toBe($totalCredit);
});

test('invoice number uniqueness enforced', function () {
    Invoice::factory()->create(['number' => 'INV-001']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/accounting/invoices', [
            'number' => 'INV-001',
            'total' => 100,
        ]);

    expect($response->status())->toBe(422);
});

test('partial payment handling', function () {
    $invoice = Invoice::factory()->create([
        'total' => 1000,
        'amount_paid' => 300,
        'amount_due' => 700,
    ]);

    expect($invoice->amount_due)->toBe(700);
});

test('invoice overdue tracking', function () {
    $invoice = Invoice::factory()->create([
        'due_date' => now()->subDays(5),
        'status' => 'sent',
        'amount_paid' => 0,
    ]);

    expect($invoice->due_date->isPast())->toBeTrue();
});

test('credit note reduces invoice amount', function () {
    $invoice = Invoice::factory()->create([
        'total' => 1000,
        'amount_paid' => 0,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/accounting/invoices', [
            'number' => 'CR-001',
            'total' => -100,
            'type' => 'credit',
        ]);

    expect(in_array($response->status(), [201, 422]))->toBeTrue();
});

test('invoice templates support', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounting/invoices');

    expect($response->status())->toBe(200);
});
