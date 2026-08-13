<?php

use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;

describe('Invoice API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list invoices', function () {
        Invoice::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['*' => ['id', 'number', 'total']]]);
    });

    test('can create an invoice', function () {
        $journal = Journal::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/invoices', [
                'number' => 'INV-001',
                'journal_id' => $journal->id,
                'type' => 'invoice',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'tax_amount' => 100,
                'total' => 1100,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.number', 'INV-001');

        $this->assertDatabaseHas('acc_invoices', ['number' => 'INV-001']);
    });

    test('can record payment on invoice', function () {
        $invoice = Invoice::factory()->create(['total' => 1000, 'amount_paid' => 0]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/invoices/{$invoice->id}/payment", [
                'amount' => 500,
            ]);

        $response->assertStatus(200);
        expect((float) $invoice->fresh()->amount_paid)->toBe(500.0);
    });

    test('can get outstanding invoices', function () {
        Invoice::factory()->create(['status' => 'received', 'amount_paid' => 0, 'total' => 1000]);
        Invoice::factory()->create(['status' => 'paid']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/invoices/outstanding');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    test('can get overdue invoices', function () {
        Invoice::factory()->create([
            'due_date' => now()->subDays(10),
            'status' => 'received',
            'amount_paid' => 0,
            'total' => 1000,
        ]);
        Invoice::factory()->create(['due_date' => now()->addDays(10)]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/invoices/overdue');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });
});
