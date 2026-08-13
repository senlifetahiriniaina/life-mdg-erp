<?php

use Modules\Accounting\Models\GLAccount;

describe('GL Account API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list GL accounts', function () {
        GLAccount::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/gl-accounts');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    });

    test('can create a GL account', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/gl-accounts', [
                'account_number' => '1000',
                'account_name' => 'Cash',
                'account_type' => 'asset',
                'normal_balance' => 'debit',
                'description' => 'Cash account',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.account_name', 'Cash');

        $this->assertDatabaseHas('acc_gl_accounts', ['account_number' => '1000']);
    });

    test('can filter accounts by type', function () {
        GLAccount::factory()->create(['account_type' => 'asset']);
        GLAccount::factory()->count(3)->create(['account_type' => 'liability']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/gl-accounts?type=asset');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });
});
