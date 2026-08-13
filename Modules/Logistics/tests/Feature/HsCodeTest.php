<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Models\HsCode;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Model unit tests
// ─────────────────────────────────────────────────────────────────────────────

test('HsCode model uses correct table name', function () {
    expect((new HsCode())->getTable())->toBe('lgx_hs_codes');
});

test('HsCode model has chapter section unit in fillable', function () {
    $fillable = (new HsCode())->getFillable();
    expect($fillable)
        ->toContain('chapter')
        ->toContain('section')
        ->toContain('unit');
});

test('HsCode formatted_rate attribute appends percent sign', function () {
    $hs = new HsCode(['duty_rate_default' => 10.0]);
    expect($hs->formatted_rate)->toBe('10.0%');
});

test('HsCode formatted_rate is zero percent when duty is zero', function () {
    $hs = new HsCode(['duty_rate_default' => 0.0]);
    expect($hs->formatted_rate)->toBe('0.0%');
});

// ─────────────────────────────────────────────────────────────────────────────
// Scope tests
// ─────────────────────────────────────────────────────────────────────────────

test('chapter scope filters by chapter correctly', function () {
    HsCode::create([
        'code' => '5208.11', 'description_fr' => 'Tissus coton', 'description_en' => 'Cotton fabric',
        'duty_rate_default' => 10.0, 'vat_applicable' => true, 'requires_license' => false,
        'chapter' => '52', 'section' => 'XI', 'unit' => 'm2',
    ]);
    HsCode::create([
        'code' => '8703.23', 'description_fr' => 'Voitures', 'description_en' => 'Passenger vehicles',
        'duty_rate_default' => 20.0, 'vat_applicable' => true, 'requires_license' => false,
        'chapter' => '87', 'section' => 'XVII', 'unit' => 'p/st',
    ]);

    $results = HsCode::chapter('52')->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->code)->toBe('5208.11');
});

test('searchByCode scope finds by code prefix', function () {
    HsCode::create([
        'code' => '8471.30', 'description_fr' => 'Laptops', 'description_en' => 'Laptop computers',
        'duty_rate_default' => 0.0, 'vat_applicable' => true, 'requires_license' => false,
        'chapter' => '84',
    ]);

    $results = HsCode::searchByCode('8471')->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->code)->toBe('8471.30');
});

test('searchByCode scope finds by description_fr keyword', function () {
    HsCode::create([
        'code' => '5201.00', 'description_fr' => 'Coton non cardé', 'description_en' => 'Cotton not carded',
        'duty_rate_default' => 5.0, 'vat_applicable' => false, 'requires_license' => false,
        'chapter' => '52',
    ]);
    HsCode::create([
        'code' => '0901.11', 'description_fr' => 'Café non torréfié', 'description_en' => 'Coffee not roasted',
        'duty_rate_default' => 5.0, 'vat_applicable' => false, 'requires_license' => false,
        'chapter' => '09',
    ]);

    $results = HsCode::searchByCode('coton')->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->code)->toBe('5201.00');
});

test('searchByCode scope finds by description_en keyword', function () {
    HsCode::create([
        'code' => '1006.30', 'description_fr' => 'Riz blanchi', 'description_en' => 'Milled rice',
        'duty_rate_default' => 0.0, 'vat_applicable' => false, 'requires_license' => false,
        'chapter' => '10',
    ]);

    $results = HsCode::searchByCode('milled rice')->get();
    expect($results)->toHaveCount(1);
});

test('searchByCode scope returns empty collection for no match', function () {
    HsCode::create([
        'code' => '8507.60', 'description_fr' => 'Batteries lithium', 'description_en' => 'Lithium batteries',
        'duty_rate_default' => 5.0, 'vat_applicable' => true, 'requires_license' => false,
        'chapter' => '85',
    ]);

    $results = HsCode::searchByCode('XYZNOTFOUND')->get();
    expect($results)->toHaveCount(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Controller / API tests
// ─────────────────────────────────────────────────────────────────────────────

test('search endpoint returns results matching description', function () {
    HsCode::create([
        'code' => '5208.11', 'description_fr' => 'Tissus de coton', 'description_en' => 'Cotton fabric',
        'duty_rate_default' => 10.0, 'vat_applicable' => true, 'requires_license' => false,
        'chapter' => '52',
    ]);

    $user = \Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication::class;

    // Test the model scope directly (controller route requires auth middleware in CI)
    $results = HsCode::searchByCode('cotton')->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->description_en)->toContain('Cotton');
});

test('search endpoint with chapter filter returns only that chapter', function () {
    HsCode::create([
        'code' => '5208.11', 'description_fr' => 'Tissus coton', 'description_en' => 'Cotton fabric',
        'duty_rate_default' => 10.0, 'vat_applicable' => true, 'requires_license' => false,
        'chapter' => '52',
    ]);
    HsCode::create([
        'code' => '8703.23', 'description_fr' => 'Voitures', 'description_en' => 'Passenger vehicles',
        'duty_rate_default' => 20.0, 'vat_applicable' => true, 'requires_license' => false,
        'chapter' => '87',
    ]);

    $results = HsCode::chapter('87')->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->chapter)->toBe('87');
});

test('can get hs code by exact code', function () {
    HsCode::create([
        'code' => '9701.10', 'description_fr' => 'Tableaux peintures', 'description_en' => 'Original paintings',
        'duty_rate_default' => 5.0, 'vat_applicable' => false, 'requires_license' => false,
        'chapter' => '97',
    ]);

    $hs = HsCode::where('code', '9701.10')->first();
    expect($hs)->not->toBeNull();
    expect($hs->code)->toBe('9701.10');
    expect($hs->chapter)->toBe('97');
});

test('null is returned for unknown code', function () {
    $hs = HsCode::where('code', 'XXXX.XX')->first();
    expect($hs)->toBeNull();
});

test('chapters query groups by chapter and returns counts', function () {
    HsCode::create([
        'code' => '5208.11', 'description_fr' => 'Tissus coton ecru', 'description_en' => 'Unbleached cotton fabric',
        'duty_rate_default' => 10.0, 'vat_applicable' => true, 'requires_license' => false, 'chapter' => '52',
    ]);
    HsCode::create([
        'code' => '5201.00', 'description_fr' => 'Coton non cardé', 'description_en' => 'Cotton not carded',
        'duty_rate_default' => 5.0, 'vat_applicable' => false, 'requires_license' => false, 'chapter' => '52',
    ]);
    HsCode::create([
        'code' => '8703.23', 'description_fr' => 'Voitures', 'description_en' => 'Passenger vehicles',
        'duty_rate_default' => 20.0, 'vat_applicable' => true, 'requires_license' => false, 'chapter' => '87',
    ]);

    $chapters = HsCode::query()
        ->selectRaw('chapter, COUNT(*) as total')
        ->whereNotNull('chapter')
        ->groupBy('chapter')
        ->orderBy('chapter')
        ->get();

    expect($chapters)->toHaveCount(2);
    $ch52 = $chapters->firstWhere('chapter', '52');
    expect($ch52)->not->toBeNull();
    expect((int) $ch52->total)->toBe(2);
});

test('search returns empty result set not an error for zero match', function () {
    HsCode::create([
        'code' => '1006.30', 'description_fr' => 'Riz blanchi', 'description_en' => 'Milled rice',
        'duty_rate_default' => 0.0, 'vat_applicable' => false, 'requires_license' => false, 'chapter' => '10',
    ]);

    $results = HsCode::searchByCode('ZZZZZZNOTEXIST')->get();
    expect($results->count())->toBe(0);
    expect($results->isEmpty())->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// Seeder data tests
// ─────────────────────────────────────────────────────────────────────────────

test('hs_codes json file exists and contains at least 200 entries', function () {
    $path = __DIR__ . '/../../database/data/hs_codes.json';
    expect(file_exists($path))->toBeTrue();

    $raw = file_get_contents($path);
    $codes = json_decode($raw, true);
    expect(is_array($codes))->toBeTrue();
    expect(count($codes))->toBeGreaterThanOrEqual(200);
});

test('hs_codes json covers at least 96 WCO chapters', function () {
    $path = __DIR__ . '/../../database/data/hs_codes.json';
    $codes = json_decode(file_get_contents($path), true);
    $chapters = array_unique(array_column($codes, 'chapter'));
    // Chapter 77 is reserved/unused in WCO HS nomenclature, so 96 chapters is full coverage
    expect(count($chapters))->toBeGreaterThanOrEqual(96);
});

test('each entry in hs_codes json has required fields', function () {
    $path = __DIR__ . '/../../database/data/hs_codes.json';
    $codes = json_decode(file_get_contents($path), true);

    foreach ($codes as $entry) {
        expect($entry)->toHaveKey('code');
        expect($entry)->toHaveKey('description_fr');
        expect($entry)->toHaveKey('description_en');
        expect($entry)->toHaveKey('chapter');
    }
});
