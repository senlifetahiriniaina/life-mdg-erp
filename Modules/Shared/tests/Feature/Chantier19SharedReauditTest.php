<?php

/**
 * Chantier 19 Lot 3 (Shared re-verification, empirical-execution
 * methodology): actually calls the real HTTP routes / trait boot logic
 * against real seeded data.
 *
 *  1. CurrencyController::convert() — the real math confirmed for real
 *     currency pairs (matching the exact figures already documented in
 *     CLAUDE.md's Chantier 17 entry: 4.5 EUR -> ~22 011 MGA, 3.8 USD ->
 *     17 100 MGA), not just "it returns something".
 *  2. CountryController::index()/show() — queried columns that have never
 *     existed on shared_countries (`active`, `code`, `ohada_member`), a
 *     bug camouflaged as an always-empty result rather than a loud SQL
 *     error (SQLite/MySQL both silently treat an unrecognized
 *     double-quoted identifier as a string literal instead of throwing).
 *     Confirmed empirically, fixed, and shared_countries seeded for the
 *     first time (the identical never-seeded gap already found and fixed
 *     for shared_currencies in Chantier 17).
 *  3. Modules\Shared\Traits\MultiTenantScope — the client-controlled
 *     X-Company-ID header-fallback IDOR (dropped, matching the identical
 *     fix applied to Setting/SettingsService in the same pass). Zero real
 *     model in this app currently uses this trait, so it's exercised here
 *     via a small anonymous Eloquent model bound to a real table.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Models\Currency;

uses(RefreshDatabase::class);

function chantier19SharedUser(Company $company, string $role = 'employee'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

test('convert() computes the real EUR->MGA and USD->MGA math against the seeded illustrative rates', function () {
    test()->seed(\Database\Seeders\DefaultDataSeeder::class);
    $company = Company::factory()->create();
    $user = chantier19SharedUser($company);

    $eur = test()->actingAs($user, 'sanctum')->postJson('/api/v1/shared/currencies/convert', [
        'amount' => 4.5, 'from' => 'EUR', 'to' => 'MGA',
    ])->assertOk();
    expect((float) $eur->json('data.converted_amount'))->toEqual(22011.0);

    $usd = test()->actingAs($user, 'sanctum')->postJson('/api/v1/shared/currencies/convert', [
        'amount' => 3.8, 'from' => 'USD', 'to' => 'MGA',
    ])->assertOk();
    expect((float) $usd->json('data.converted_amount'))->toEqual(17100.0);
});

test('convert() 422s cleanly for a currency with no recorded exchange rate rather than dividing by null', function () {
    $company = Company::factory()->create();
    $user = chantier19SharedUser($company);
    Currency::create(['code' => 'ZZZ', 'name' => 'No Rate', 'symbol' => 'Z', 'decimals' => 2, 'is_active' => true, 'exchange_rate_to_usd' => null]);
    Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'is_active' => true, 'exchange_rate_to_usd' => 1]);

    test()->actingAs($user, 'sanctum')->postJson('/api/v1/shared/currencies/convert', [
        'amount' => 10, 'from' => 'ZZZ', 'to' => 'USD',
    ])->assertStatus(422);
});

test('CountryController::index()/show() query the real schema and return real seeded data', function () {
    test()->seed(\Database\Seeders\DefaultDataSeeder::class);
    $company = Company::factory()->create();
    $user = chantier19SharedUser($company);

    $index = test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/countries')->assertOk();
    expect($index->json('data'))->toHaveCount(12);

    $show = test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/countries/MG')->assertOk();
    expect($show->json('data.iso_alpha2'))->toBe('MG');
    expect($show->json('data.currency_code'))->toBe('MGA');

    // 3-letter code also resolves (iso_alpha3).
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/countries/MDG')->assertOk()
        ->assertJsonPath('data.iso_alpha2', 'MG');

    $ohada = test()->actingAs($user, 'sanctum')->getJson('/api/v1/shared/countries?ohada=1')->assertOk();
    expect(collect($ohada->json('data'))->pluck('iso_alpha2')->sort()->values()->all())->toBe(['CI', 'CM', 'SN']);
});

test('MultiTenantScope no longer honors a client-controlled X-Company-ID header for a user with no real company', function () {
    $modelClass = new class extends \Illuminate\Database\Eloquent\Model {
        use \Modules\Shared\Traits\MultiTenantScope;

        protected $table = 'chantier19_mts_probe';
        protected $fillable = ['company_id', 'label'];
        public $timestamps = false;
    };

    \Illuminate\Support\Facades\Schema::create('chantier19_mts_probe', function ($t) {
        $t->id();
        $t->unsignedBigInteger('company_id')->nullable();
        $t->string('label');
    });

    $companyVictim = Company::factory()->create();
    $modelClass::query()->getModel()->newQuery()->getConnection();
    (new $modelClass)->newQuery()->insert(['company_id' => $companyVictim->id, 'label' => 'victim row']);

    // Attacker: no company_id of their own.
    $attacker = User::factory()->create(['company_id' => null]);
    test()->actingAs($attacker, 'sanctum');
    request()->headers->set('X-Company-ID', (string) $companyVictim->id);

    // Before the fix this would have returned the victim company's row via
    // the spoofed header; after the fix, a null-company caller only ever
    // sees rows with no company (whereNull) — the header is never trusted.
    expect($modelClass::query()->count())->toBe(0);

    \Illuminate\Support\Facades\Schema::dropIfExists('chantier19_mts_probe');
});
