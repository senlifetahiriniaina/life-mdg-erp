<?php

use Modules\Accounting\Models\Expense;
use Modules\Accounting\Models\GLAccount;

describe('Expense API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can record an expense', function () {
        $account = GLAccount::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/expenses', [
                'expense_reference' => 'EXP-001',
                'gl_account_id' => $account->id,
                'expense_date' => now()->toDateString(),
                'category' => 'supplies',
                'description' => 'Office supplies',
                'amount' => 250,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.expense_reference', 'EXP-001');

        $this->assertDatabaseHas('acc_expenses', ['expense_reference' => 'EXP-001']);
    });

    test('can approve an expense', function () {
        $expense = Expense::factory()->create(['status' => 'recorded']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/expenses/{$expense->id}/approve");

        $response->assertStatus(200);
        expect($expense->fresh()->status)->toBe('approved');
    });

    test('can get pending expenses', function () {
        Expense::factory()->create(['status' => 'recorded']);
        Expense::factory()->count(2)->create(['status' => 'approved']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/expenses/pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    test('can filter expenses by category', function () {
        Expense::factory()->create(['category' => 'supplies']);
        Expense::factory()->count(2)->create(['category' => 'travel']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/expenses/by-category/supplies');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });
});
