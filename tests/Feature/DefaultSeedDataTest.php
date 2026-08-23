<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Achats\Models\Supplier;
use Modules\Inventory\Models\Product;
use Modules\Setup\Models\CompanyProfile;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('default seed provides a working admin with every role', function () {
    $this->seed();

    $admin = User::where('email', 'admin@life-mdg.com')->first();

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

test('default seed provides raw-material and finished-good products, and compensation accounts', function () {
    $this->seed();

    expect(Product::where('sku', 'MP-DEFAUT')->exists())->toBeTrue();
    expect(Product::where('sku', 'PF-DEFAUT')->exists())->toBeTrue();
    expect(Product::where('sku', 'MD-DEFAUT')->exists())->toBeTrue();
    expect(Product::where('sku', 'SV-DEFAUT')->exists())->toBeTrue();

    // Chantier 36: remapped onto the real chart — 6032 (matières
    // premières/accessoires), 6031 (marchandises), 736 (produits finis).
    expect(DB::table('acc_chart_of_accounts')->where('code', '6032')->exists())->toBeTrue();
    expect(DB::table('acc_chart_of_accounts')->where('code', '6031')->exists())->toBeTrue();
    expect(DB::table('acc_chart_of_accounts')->where('code', '736')->exists())->toBeTrue();
});

test('default company profile is configured as a VAT-exempt SARL', function () {
    $this->seed();

    $profile = CompanyProfile::where('country_code', 'MG')->first();

    expect($profile)->not->toBeNull();
    expect($profile->vat_exempt)->toBeTrue();
    expect($profile->vat_number)->toBeNull();
});

test('import job splits a full_name mapping into first_name/last_name', function () {
    $job = (new ReflectionClass(\Modules\Setup\Jobs\ImportDataJob::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($job, 'splitFullName');
    $method->setAccessible(true);

    expect($method->invoke($job, 'Rakoto Andrianina'))->toBe(['Rakoto', 'Andrianina']);
    expect($method->invoke($job, 'Rakoto'))->toBe(['Rakoto', '']);
    expect($method->invoke($job, ''))->toBe(['', '']);
});
