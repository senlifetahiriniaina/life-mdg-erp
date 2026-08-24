<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\AccountRoleService;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 38.2 — second-pass 14-layer deep audit of AccountRoleService
 * (Chantier 37) and its 4 real call sites, plus a full repo-wide sweep of
 * Modules\Accounting for anything new since Chantier 32.13-32.16/36/37.
 *
 * AccountRoleService itself was already well covered by Chantier 37's own
 * AccountRoleServiceTest.php (default resolution, override, invalid/inactive
 * code rejection, unresolvable throw, listRoles(), RBAC, web page) — this
 * file adds the layers that test was missing: real per-tenant isolation of
 * an override (SettingsService::currentTenantId() resolves via
 * auth()->user()->company_id, never verified across two real companies
 * before), and the real paginated-response contract the two consumer Vue
 * pages (Accounting/AccountRoles/Index.vue, ChartOfAccounts/Index.vue) both
 * depend on to page through the real ~209-account Chantier-36 chart.
 */
function chantier382User(string $role = 'accountant'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    if (ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $company = Company::create([
        'name'     => 'Chantier382 Co '.uniqid(),
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    test()->actingAs($user, 'sanctum');

    return $user;
}

describe('AccountRoleService — per-tenant isolation (never verified before this chantier)', function () {
    test('an override set by one real company never leaks to another real company sharing the same default', function () {
        $userA = chantier382User('accountant');
        app(AccountRoleService::class)->setRole('default_treasury_account', '57');
        expect(app(AccountRoleService::class)->resolveAccount('default_treasury_account')->code)->toBe('57');

        // Switch to a genuinely different real company (auth()->user()->company_id
        // changes, which is the only thing SettingsService::currentTenantId()
        // resolves on).
        chantier382User('accountant');
        expect(app(AccountRoleService::class)->resolveAccount('default_treasury_account')->code)
            ->toBe('52'); // the real Chantier 36 default, not company A's '57' override.

        // Company B sets its own, different override.
        app(AccountRoleService::class)->setRole('default_treasury_account', '5211');
        expect(app(AccountRoleService::class)->resolveAccount('default_treasury_account')->code)->toBe('5211');

        // Switch back to company A and confirm its override is still intact,
        // unaffected by company B's own write.
        test()->actingAs($userA, 'sanctum');
        expect(app(AccountRoleService::class)->resolveAccount('default_treasury_account')->code)->toBe('57');
    });

    test('the GET account-roles endpoint reflects the caller\'s own company override, not another company\'s', function () {
        chantier382User('accountant');
        app(AccountRoleService::class)->setRole('default_clients_account', '4191');

        $ownResponse = test()->getJson('/api/v1/accounting/account-roles');
        $own = collect($ownResponse->json('data'))->firstWhere('role', 'default_clients_account');
        expect($own['resolved_code'])->toBe('4191');
        expect($own['is_customized'])->toBeTrue();

        // A second real company, never customized — sees the real default,
        // not company A's override.
        chantier382User('accountant');
        $otherResponse = test()->getJson('/api/v1/accounting/account-roles');
        $other = collect($otherResponse->json('data'))->firstWhere('role', 'default_clients_account');
        expect($other['resolved_code'])->toBe('41');
        expect($other['is_customized'])->toBeFalse();
    });
});

describe('ChartOfAccountController::index() — the real paginated-resource shape both consumer Vue pages depend on', function () {
    test('with per_page=100 against the real ~209-account Chantier-36 chart, pagination metadata lives under meta, not the top level', function () {
        chantier382User('accountant');

        $response = test()->getJson('/api/v1/accounting/chart-of-accounts?is_active=true&per_page=100&page=1');
        $response->assertStatus(200);

        $json = $response->json();

        // Chantier 38.2: both Accounting/AccountRoles/Index.vue (fetchAccounts())
        // and Accounting/ChartOfAccounts/Index.vue (fetchAccounts()) used to read
        // a top-level `last_page`/`current_page`/`total` that has never existed on
        // this response — ChartOfAccountResource::collection(...)->response()
        // ->getData(true) produces Laravel's standard {data, links, meta} shape.
        // Locking in the real contract here is what both fixes now depend on.
        expect($json)->toHaveKeys(['data', 'links', 'meta']);
        expect($json)->not->toHaveKey('last_page');
        expect($json)->not->toHaveKey('current_page');
        expect($json)->not->toHaveKey('total');
        expect($json['meta'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);

        expect(count($json['data']))->toBe(100);
        // The real Chantier-36 chart has ~209 active accounts — strictly more
        // than one page at per_page=100, so a real multi-page loop reading
        // meta.last_page correctly walks every page instead of silently
        // stopping after the first 100.
        expect($json['meta']['last_page'])->toBeGreaterThan(1);
        expect($json['meta']['total'])->toBe(ChartOfAccount::where('is_active', true)->count());

        // Walk every page the same way the fixed Vue loops now do, and
        // confirm the full set is reachable (no accounts silently dropped).
        $seen = collect($json['data'])->pluck('code');
        $lastPage = $json['meta']['last_page'];
        for ($page = 2; $page <= $lastPage; $page++) {
            $pageResponse = test()->getJson("/api/v1/accounting/chart-of-accounts?is_active=true&per_page=100&page={$page}");
            $seen = $seen->merge(collect($pageResponse->json('data'))->pluck('code'));
        }
        expect($seen->unique()->count())->toBe($json['meta']['total']);
    });
});

describe('The 4 real AccountRoleService call sites resolve every one of the 8 default codes against the real seeded chart', function () {
    test('all 8 default codes are real, active accounts (re-verified against the current chart, not assumed)', function () {
        chantier382User('accountant');
        $service = app(AccountRoleService::class);

        foreach ([
            'default_treasury_account', 'default_clients_account', 'avances_recues_clients',
            'default_suppliers_account', 'avances_versees_fournisseurs',
            'personnel_remuneration_expense', 'salary_payable_liability', 'irsa_withholding_liability',
        ] as $role) {
            $account = $service->resolveAccount($role);
            expect($account->is_active)->toBeTrue();
            expect($account->id)->toBeGreaterThan(0);
        }
    });
});
