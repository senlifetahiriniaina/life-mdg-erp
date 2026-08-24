<?php

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\AccountRoleService;

/**
 * Chantier 37 — AccountRoleService: account codes for the 8 roles Chantier
 * 36 hardcoded (default treasury/clients/suppliers/deposit-clearing/payroll
 * accounts) are now configurable via Modules\Settings, with a static
 * fallback table matching those exact hardcoded values so a tenant that
 * never customizes anything sees zero behavior change.
 */
beforeEach(function () {
    $this->user = actingAsUser('accountant');

    if (\Modules\Accounting\Models\ChartOfAccount::count() === 0) {
        $this->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }
});

describe('AccountRoleService::resolveAccount() — defaults with zero customization', function () {
    test('every one of the 8 roles resolves to its documented default code', function () {
        $service = app(AccountRoleService::class);

        $defaults = [
            'default_treasury_account' => '52',
            'default_clients_account' => '41',
            'avances_recues_clients' => '419',
            'default_suppliers_account' => '40',
            'avances_versees_fournisseurs' => '4091',
            'personnel_remuneration_expense' => '661',
            'salary_payable_liability' => '422',
            'irsa_withholding_liability' => '4471',
        ];

        foreach ($defaults as $role => $code) {
            $account = $service->resolveAccount($role);
            expect($account->code)->toBe($code);
        }
    });
});

describe('AccountRoleService::setRole() — tenant override', function () {
    test('an override changes what resolveAccount() returns', function () {
        $service = app(AccountRoleService::class);

        expect($service->resolveAccount('default_treasury_account')->code)->toBe('52');

        $service->setRole('default_treasury_account', '57');

        expect($service->resolveAccount('default_treasury_account')->code)->toBe('57');
        // Other roles are untouched by this one override.
        expect($service->resolveAccount('default_clients_account')->code)->toBe('41');
    });

    test('setRole() via the API rejects an unknown/inactive code and writes nothing', function () {
        $response = $this->putJson('/api/v1/accounting/account-roles/default_treasury_account', [
            'code' => 'NOPE-999',
        ]);

        $response->assertStatus(422);

        $service = app(AccountRoleService::class);
        expect($service->resolveAccount('default_treasury_account')->code)->toBe('52');
    });

    test('setRole() rejects an inactive account even if the code exists', function () {
        ChartOfAccount::factory()->create(['code' => '999999', 'name' => 'Inactif', 'type' => 'asset', 'is_active' => false]);

        $response = $this->putJson('/api/v1/accounting/account-roles/default_treasury_account', [
            'code' => '999999',
        ]);

        $response->assertStatus(422);
    });
});

describe('AccountRoleService::resolveAccount() — unresolvable throws', function () {
    test('resolveAccount() throws a RuntimeException when the resolved code has no active account', function () {
        ChartOfAccount::where('code', '52')->update(['is_active' => false]);

        $service = app(AccountRoleService::class);

        expect(fn () => $service->resolveAccount('default_treasury_account'))
            ->toThrow(RuntimeException::class);
    });
});

describe('AccountRoleService::listRoles()', function () {
    test('reports is_customized/is_resolvable/resolved_account_name correctly and never 500s when unresolvable', function () {
        app(AccountRoleService::class)->setRole('default_treasury_account', '57');
        ChartOfAccount::where('code', '4471')->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/accounting/account-roles');

        $response->assertStatus(200);
        $roles = collect($response->json('data'));

        $treasury = $roles->firstWhere('role', 'default_treasury_account');
        expect($treasury['is_customized'])->toBeTrue();
        expect($treasury['is_resolvable'])->toBeTrue();
        expect($treasury['resolved_code'])->toBe('57');
        expect($treasury['resolved_account_name'])->toBe('Caisse');

        $irsa = $roles->firstWhere('role', 'irsa_withholding_liability');
        expect($irsa['is_resolvable'])->toBeFalse();
        expect($irsa['resolved_account_id'])->toBeNull();
        expect($irsa['is_customized'])->toBeFalse();
    });
});

describe('RBAC', function () {
    test('a role without accounting access is denied on GET and PUT', function () {
        actingAsUser('sales-rep');

        $this->getJson('/api/v1/accounting/account-roles')->assertStatus(403);
        $this->putJson('/api/v1/accounting/account-roles/default_treasury_account', ['code' => '57'])->assertStatus(403);
    });
});

describe('Web page', function () {
    test('GET /accounting/account-roles is reachable and renders the real Inertia component', function () {
        $response = $this->get('/accounting/account-roles');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Accounting/AccountRoles/Index'));
    });
});
