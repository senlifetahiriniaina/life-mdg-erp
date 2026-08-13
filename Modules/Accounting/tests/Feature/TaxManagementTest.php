<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Accounting\Models\TaxEntry;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\TaxService;

// ─── Unauthenticated access (outside describe) ────────────────────────────────

test('unauthenticated user cannot access tax-rates index', function () {
    $response = $this->getJson('/api/v1/accounting/tax-rates');
    $response->assertStatus(401);
});

test('unauthenticated user cannot access active tax-rates', function () {
    $response = $this->getJson('/api/v1/accounting/tax-rates/active');
    $response->assertStatus(401);
});

test('unauthenticated user cannot access tax report', function () {
    $response = $this->getJson('/api/v1/accounting/tax/report?period_start=2026-01-01&period_end=2026-03-31');
    $response->assertStatus(401);
});

// ─── TaxRate Model ────────────────────────────────────────────────────────────

describe('TaxRate Model', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('TaxRate::isActive() returns true when is_active is true', function () {
        $rate = TaxRate::factory()->create(['is_active' => true]);
        expect($rate->isActive())->toBeTrue();
    });

    test('TaxRate::isActive() returns false when is_active is false', function () {
        $rate = TaxRate::factory()->create(['is_active' => false]);
        expect($rate->isActive())->toBeFalse();
    });

    test('TaxRate::isCompound() returns true when is_compound is true', function () {
        $rate = TaxRate::factory()->create(['is_compound' => true]);
        expect($rate->isCompound())->toBeTrue();
    });

    test('TaxRate::isCompound() returns false when is_compound is false', function () {
        $rate = TaxRate::factory()->create(['is_compound' => false]);
        expect($rate->isCompound())->toBeFalse();
    });

    test('TaxRate::calculateTax() computes correct tax amount', function () {
        $rate = TaxRate::factory()->create(['rate' => '20.0000']);
        $tax = $rate->calculateTax(500.0);
        expect($tax)->toEqual(100.0);
    });

    test('TaxRate::calculateTax() works with fractional rates', function () {
        $rate = TaxRate::factory()->create(['rate' => '7.5000']);
        $tax = $rate->calculateTax(200.0);
        expect($tax)->toEqual(15.0);
    });

    test('TaxRate::activate() sets is_active to true', function () {
        $rate = TaxRate::factory()->create(['is_active' => false]);
        $rate->activate();
        expect($rate->fresh()->is_active)->toBeTrue();
    });

    test('TaxRate::deactivate() sets is_active to false', function () {
        $rate = TaxRate::factory()->create(['is_active' => true]);
        $rate->deactivate();
        expect($rate->fresh()->is_active)->toBeFalse();
    });

    test('TaxRate::getEffectiveRate() returns float rate', function () {
        $rate = TaxRate::factory()->create(['rate' => '15.5000']);
        expect($rate->getEffectiveRate())->toEqual(15.5);
    });

    test('TaxRate has entries relationship', function () {
        $rate = TaxRate::factory()->create();
        $entry = TaxEntry::factory()->create(['tax_rate_id' => $rate->id]);
        expect($rate->entries()->count())->toBe(1);
        expect($rate->entries->first()->id)->toBe($entry->id);
    });
});

// ─── TaxEntry Model ───────────────────────────────────────────────────────────

describe('TaxEntry Model', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('TaxEntry::isCollected() returns true for collected type', function () {
        $entry = TaxEntry::factory()->create(['type' => 'collected']);
        expect($entry->isCollected())->toBeTrue();
        expect($entry->isPaid())->toBeFalse();
    });

    test('TaxEntry::isPaid() returns true for paid type', function () {
        $entry = TaxEntry::factory()->create(['type' => 'paid']);
        expect($entry->isPaid())->toBeTrue();
        expect($entry->isCollected())->toBeFalse();
    });

    test('TaxEntry::netTaxLiability() calculates correctly', function () {
        $rate = TaxRate::factory()->create(['rate' => '10.0000']);
        $entry = TaxEntry::factory()->create([
            'tax_rate_id' => $rate->id,
            'taxable_amount' => 1000.0,
        ]);
        expect($entry->netTaxLiability())->toEqual(100.0);
    });

    test('TaxEntry belongs to TaxRate', function () {
        $rate = TaxRate::factory()->create();
        $entry = TaxEntry::factory()->create(['tax_rate_id' => $rate->id]);
        expect($entry->taxRate->id)->toBe($rate->id);
    });

    test('TaxEntry period_start and period_end are cast as dates', function () {
        $entry = TaxEntry::factory()->create([
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
        ]);
        expect($entry->period_start)->toBeInstanceOf(Carbon::class);
        expect($entry->period_end)->toBeInstanceOf(Carbon::class);
    });
});

// ─── TaxService ───────────────────────────────────────────────────────────────

describe('TaxService', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(TaxService::class);
    });

    test('createTaxRate() creates and returns a TaxRate', function () {
        $rate = $this->service->createTaxRate([
            'name' => 'Standard VAT',
            'code' => 'VAT_STD',
            'rate' => 20.0,
            'type' => 'vat',
            'is_active' => true,
            'is_compound' => false,
            'applies_to' => 'all',
        ]);

        expect($rate)->toBeInstanceOf(TaxRate::class);
        expect($rate->code)->toBe('VAT_STD');
        $this->assertDatabaseHas('acc_tax_rates', ['code' => 'VAT_STD']);
    });

    test('calculateTax() delegates to rate->calculateTax()', function () {
        $rate = TaxRate::factory()->create(['rate' => '25.0000']);
        $result = $this->service->calculateTax(400.0, $rate);
        expect($result)->toEqual(100.0);
    });

    test('calculateCompoundTax() applies rates in sequence', function () {
        $rate1 = TaxRate::factory()->create(['rate' => '10.0000']);
        $rate2 = TaxRate::factory()->create(['rate' => '5.0000']);
        $result = $this->service->calculateCompoundTax(100.0, [$rate1, $rate2]);

        // First: 100 * 10% = 10; running = 110
        // Second: 110 * 5% = 5.5
        expect($result['breakdown'])->toHaveCount(2);
        expect($result['breakdown'][0]['tax_amount'])->toEqual(10.0);
        expect($result['breakdown'][1]['tax_amount'])->toEqual(5.5);
        expect($result['total_tax'])->toEqual(15.5);
    });

    test('calculateCompoundTax() returns correct rate_id and rate_name', function () {
        $rate = TaxRate::factory()->create(['name' => 'My Tax', 'rate' => '10.0000']);
        $result = $this->service->calculateCompoundTax(200.0, [$rate]);

        expect($result['breakdown'][0]['rate_id'])->toBe($rate->id);
        expect($result['breakdown'][0]['rate_name'])->toBe('My Tax');
    });

    test('recordTaxEntry() persists a TaxEntry', function () {
        $rate = TaxRate::factory()->create();
        $entry = $this->service->recordTaxEntry([
            'tax_rate_id' => $rate->id,
            'taxable_amount' => 500.0,
            'tax_amount' => 100.0,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'type' => 'collected',
        ]);

        expect($entry)->toBeInstanceOf(TaxEntry::class);
        $this->assertDatabaseHas('acc_tax_entries', ['tax_rate_id' => $rate->id, 'type' => 'collected']);
    });

    test('getTaxLiability() returns correct collected/paid/net values', function () {
        $rate = TaxRate::factory()->create();

        TaxEntry::factory()->create([
            'tax_rate_id' => $rate->id,
            'tax_amount' => 300.0,
            'type' => 'collected',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
        ]);

        TaxEntry::factory()->create([
            'tax_rate_id' => $rate->id,
            'tax_amount' => 100.0,
            'type' => 'paid',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
        ]);

        $result = $this->service->getTaxLiability('2026-01-01', '2026-01-31');

        expect($result['collected'])->toEqual(300.0);
        expect($result['paid'])->toEqual(100.0);
        expect($result['net_liability'])->toEqual(200.0);
    });

    test('getTaxReport() returns report structure with by_rate', function () {
        $rate = TaxRate::factory()->create(['name' => 'VAT 20%']);

        TaxEntry::factory()->create([
            'tax_rate_id' => $rate->id,
            'tax_amount' => 200.0,
            'type' => 'collected',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
        ]);

        $report = $this->service->getTaxReport('2026-02-01', '2026-02-28');

        expect($report['period_start'])->toBe('2026-02-01');
        expect($report['period_end'])->toBe('2026-02-28');
        expect($report['entries_count'])->toBe(1);
        expect($report['total_collected'])->toEqual(200.0);
        expect($report['total_paid'])->toEqual(0.0);
        expect($report['net_liability'])->toEqual(200.0);
        expect($report['by_rate'])->toHaveCount(1);
    });

    test('getActiveTaxRates() returns only active rates', function () {
        TaxRate::factory()->create(['is_active' => true]);
        TaxRate::factory()->create(['is_active' => true]);
        TaxRate::factory()->create(['is_active' => false]);

        $rates = $this->service->getActiveTaxRates();

        expect($rates->count())->toBe(2);
        expect($rates->every(fn ($r) => $r->is_active))->toBeTrue();
    });
});

// ─── Tax Rates API ────────────────────────────────────────────────────────────

describe('Tax Rates API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('GET /api/v1/accounting/tax-rates returns paginated list', function () {
        TaxRate::factory()->count(3)->create();

        $this->getJson('/api/v1/accounting/tax-rates')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page']);
    });

    test('POST /api/v1/accounting/tax-rates creates a tax rate', function () {
        $payload = [
            'name' => 'EU VAT',
            'code' => 'EU_VAT_21',
            'rate' => 21.0,
            'type' => 'vat',
            'applies_to' => 'all',
        ];

        $this->postJson('/api/v1/accounting/tax-rates', $payload)
            ->assertStatus(201)
            ->assertJsonPath('code', 'EU_VAT_21');

        $this->assertDatabaseHas('acc_tax_rates', ['code' => 'EU_VAT_21']);
    });

    test('POST /api/v1/accounting/tax-rates validates required fields', function () {
        $this->postJson('/api/v1/accounting/tax-rates', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'rate']);
    });

    test('POST /api/v1/accounting/tax-rates rejects duplicate code', function () {
        TaxRate::factory()->create(['code' => 'DUP_CODE']);

        $this->postJson('/api/v1/accounting/tax-rates', [
            'name' => 'Another Rate',
            'code' => 'DUP_CODE',
            'rate' => 10.0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    test('GET /api/v1/accounting/tax-rates/active returns only active rates', function () {
        TaxRate::factory()->create(['is_active' => true]);
        TaxRate::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/accounting/tax-rates/active')
            ->assertOk();

        $data = $response->json();
        expect(count($data))->toBe(1);
    });

    test('GET /api/v1/accounting/tax-rates/{taxRate} shows a tax rate', function () {
        $rate = TaxRate::factory()->create(['code' => 'SHOW_ME']);

        $this->getJson("/api/v1/accounting/tax-rates/{$rate->id}")
            ->assertOk()
            ->assertJsonPath('code', 'SHOW_ME');
    });

    test('PUT /api/v1/accounting/tax-rates/{taxRate} updates a tax rate', function () {
        $rate = TaxRate::factory()->create(['rate' => '10.0000']);

        $this->putJson("/api/v1/accounting/tax-rates/{$rate->id}", ['rate' => 15.0])
            ->assertOk()
            ->assertJsonPath('rate', '15.0000');
    });

    test('DELETE /api/v1/accounting/tax-rates/{taxRate} deletes a tax rate', function () {
        $rate = TaxRate::factory()->create();

        $this->deleteJson("/api/v1/accounting/tax-rates/{$rate->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('acc_tax_rates', ['id' => $rate->id]);
    });

    test('POST /api/v1/accounting/tax-rates/{taxRate}/activate activates rate', function () {
        $rate = TaxRate::factory()->create(['is_active' => false]);

        $this->postJson("/api/v1/accounting/tax-rates/{$rate->id}/activate")
            ->assertOk()
            ->assertJsonPath('is_active', true);
    });

    test('POST /api/v1/accounting/tax-rates/{taxRate}/deactivate deactivates rate', function () {
        $rate = TaxRate::factory()->create(['is_active' => true]);

        $this->postJson("/api/v1/accounting/tax-rates/{$rate->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('is_active', false);
    });
});

// ─── Tax Calculation API ──────────────────────────────────────────────────────

describe('Tax Calculation API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('POST /api/v1/accounting/tax/calculate returns tax amount', function () {
        $rate = TaxRate::factory()->create(['rate' => '20.0000']);

        $response = $this->postJson('/api/v1/accounting/tax/calculate', [
            'amount' => 500.0,
            'tax_rate_id' => $rate->id,
        ])->assertOk();

        expect((float) $response->json('tax_amount'))->toEqual(100.0);
        expect((float) $response->json('total'))->toEqual(600.0);
    });

    test('POST /api/v1/accounting/tax/calculate validates required fields', function () {
        $this->postJson('/api/v1/accounting/tax/calculate', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'tax_rate_id']);
    });

    test('POST /api/v1/accounting/tax/calculate rejects invalid tax_rate_id', function () {
        $this->postJson('/api/v1/accounting/tax/calculate', [
            'amount' => 100.0,
            'tax_rate_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['tax_rate_id']);
    });

    test('POST /api/v1/accounting/tax/calculate-compound returns breakdown', function () {
        $rate1 = TaxRate::factory()->create(['rate' => '10.0000']);
        $rate2 = TaxRate::factory()->create(['rate' => '5.0000']);

        $response = $this->postJson('/api/v1/accounting/tax/calculate-compound', [
            'amount' => 100.0,
            'rate_ids' => [$rate1->id, $rate2->id],
        ])->assertOk()
            ->assertJsonStructure(['amount', 'breakdown', 'total_tax']);

        expect((float) $response->json('total_tax'))->toEqual(15.5);
    });

    test('POST /api/v1/accounting/tax/calculate-compound validates rate_ids', function () {
        $this->postJson('/api/v1/accounting/tax/calculate-compound', [
            'amount' => 100.0,
            'rate_ids' => [99999],
        ])->assertStatus(422);
    });
});

// ─── Tax Entries API ──────────────────────────────────────────────────────────

describe('Tax Entries API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('POST /api/v1/accounting/tax/entries records a tax entry', function () {
        $rate = TaxRate::factory()->create();

        $this->postJson('/api/v1/accounting/tax/entries', [
            'tax_rate_id' => $rate->id,
            'taxable_amount' => 1000.0,
            'tax_amount' => 200.0,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'type' => 'collected',
        ])->assertStatus(201)
            ->assertJsonPath('type', 'collected')
            ->assertJsonStructure(['id', 'tax_rate_id', 'taxable_amount', 'tax_amount', 'type', 'tax_rate']);
    });

    test('POST /api/v1/accounting/tax/entries validates required fields', function () {
        $this->postJson('/api/v1/accounting/tax/entries', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_rate_id', 'taxable_amount', 'tax_amount', 'period_start', 'period_end']);
    });

    test('POST /api/v1/accounting/tax/entries validates period_end after period_start', function () {
        $rate = TaxRate::factory()->create();

        $this->postJson('/api/v1/accounting/tax/entries', [
            'tax_rate_id' => $rate->id,
            'taxable_amount' => 500.0,
            'tax_amount' => 50.0,
            'period_start' => '2026-03-31',
            'period_end' => '2026-01-01',
            'type' => 'collected',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['period_end']);
    });
});

// ─── Tax Liability & Report API ───────────────────────────────────────────────

describe('Tax Liability and Report API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');

        $this->rate = TaxRate::factory()->create();

        TaxEntry::factory()->create([
            'tax_rate_id' => $this->rate->id,
            'tax_amount' => 500.0,
            'type' => 'collected',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
        ]);

        TaxEntry::factory()->create([
            'tax_rate_id' => $this->rate->id,
            'tax_amount' => 150.0,
            'type' => 'paid',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
        ]);
    });

    test('GET /api/v1/accounting/tax/liability returns liability data', function () {
        $response = $this->getJson('/api/v1/accounting/tax/liability?period_start=2026-03-01&period_end=2026-03-31')
            ->assertOk()
            ->assertJsonStructure(['collected', 'paid', 'net_liability']);

        expect((float) $response->json('collected'))->toEqual(500.0);
        expect((float) $response->json('paid'))->toEqual(150.0);
        expect((float) $response->json('net_liability'))->toEqual(350.0);
    });

    test('GET /api/v1/accounting/tax/liability validates required params', function () {
        $this->getJson('/api/v1/accounting/tax/liability')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period_start', 'period_end']);
    });

    test('GET /api/v1/accounting/tax/report returns full report', function () {
        $response = $this->getJson('/api/v1/accounting/tax/report?period_start=2026-03-01&period_end=2026-03-31')
            ->assertOk()
            ->assertJsonStructure([
                'period_start',
                'period_end',
                'entries_count',
                'total_collected',
                'total_paid',
                'net_liability',
                'by_rate',
            ]);

        expect($response->json('entries_count'))->toBe(2);
        expect((float) $response->json('total_collected'))->toEqual(500.0);
        expect((float) $response->json('total_paid'))->toEqual(150.0);
        expect((float) $response->json('net_liability'))->toEqual(350.0);
    });

    test('GET /api/v1/accounting/tax/report validates required params', function () {
        $this->getJson('/api/v1/accounting/tax/report')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period_start', 'period_end']);
    });

    test('GET /api/v1/accounting/tax/report by_rate groups entries correctly', function () {
        $this->getJson('/api/v1/accounting/tax/report?period_start=2026-03-01&period_end=2026-03-31')
            ->assertOk()
            ->assertJsonPath('by_rate.0.tax_rate_id', $this->rate->id);
    });

    test('GET /api/v1/accounting/tax/liability returns zero when no entries in period', function () {
        $response = $this->getJson('/api/v1/accounting/tax/liability?period_start=2020-01-01&period_end=2020-01-31')
            ->assertOk();

        expect((float) $response->json('collected'))->toEqual(0.0);
        expect((float) $response->json('paid'))->toEqual(0.0);
        expect((float) $response->json('net_liability'))->toEqual(0.0);
    });
});
