<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Achats\Models\Supplier;
use Modules\Setup\Models\CompanyProfile;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('default seed provides a working admin with every role', function () {
    $this->seed();

    $admin = User::where('email', 'admin@lifemdg.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->getRoleNames())->toHaveCount(Role::where('guard_name', 'web')->count());
});

test('default seed provides a Madagascar-adapted chart of accounts and journals', function () {
    $this->seed();

    expect(DB::table('acc_chart_of_accounts')->count())->toBeGreaterThanOrEqual(76);
    expect(DB::table('acc_journals')->count())->toBe(5);
    expect(DB::table('acc_chart_of_accounts')->where('code', '447')->exists())->toBeTrue();
});

test('default seed provides a default company, customer, and supplier', function () {
    $this->seed();

    expect(Company::where('code', 'PRINCIPAL')->exists())->toBeTrue();
    expect(Customer::where('name', 'Client par défaut')->exists())->toBeTrue();
    expect(Supplier::where('code', 'FOUR-DEFAUT')->exists())->toBeTrue();
    expect(CompanyProfile::where('country_code', 'MG')->exists())->toBeTrue();
});

test('import job splits a full_name mapping into first_name/last_name', function () {
    $job = (new ReflectionClass(\Modules\Setup\Jobs\ImportDataJob::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($job, 'splitFullName');
    $method->setAccessible(true);

    expect($method->invoke($job, 'Rakoto Andrianina'))->toBe(['Rakoto', 'Andrianina']);
    expect($method->invoke($job, 'Rakoto'))->toBe(['Rakoto', '']);
    expect($method->invoke($job, ''))->toBe(['', '']);
});
