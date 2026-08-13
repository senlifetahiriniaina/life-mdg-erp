<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\EdiTransaction;
use Modules\Inventory\Services\EdiService;

uses(RefreshDatabase::class);

// ─── Fixtures ────────────────────────────────────────────────────────────────

function sample850(): string
{
    return <<<'EDI'
ISA*00*          *00*          *ZZ*SENDER         *ZZ*RECEIVER       *240101*1200*^*00501*000000001*0*P*>
GS*PO*SENDER*RECEIVER*20240101*1200*0001*X*005010X222A1
ST*850*0001
BEG*00*SA*PO12345**20240101
CUR*BY*USD
N1*BT*ACME CORP*92*ACME001
N3*123 MAIN ST
N4*NEW YORK*NY*10001*US
N1*ST*WAREHOUSE A*92*WH001
PO1*1*10*EA*25.00**VP*VEND-SKU-001*BP*BUYER-SKU-001
PID*F****Widget A
PO1*2*5*EA*50.00**VP*VEND-SKU-002*BP*BUYER-SKU-002
PID*F****Widget B
CTT*2
SE*15*0001
GE*1*0001
IEA*1*000000001
EDI;
}

function sample856(): string
{
    return <<<'EDI'
ISA*00*          *00*          *ZZ*VENDOR         *ZZ*BUYER          *240105*0900*^*00501*000000002*0*P*>
GS*SH*VENDOR*BUYER*20240105*0900*0002*X*005010X218A1
ST*856*0001
BSN*00*SHIP-001*20240105*0900
PRF*PO12345
TD1*CTN*2
TD3*MC*FEDEX*TRACK123456
N1*SF*VENDOR WAREHOUSE*92*VWH001
N1*ST*BUYER WAREHOUSE*92*BWH001
HL*1**S
HL*2*1*O
HL*3*2*P
MAN*GM*00012345678901234567
HL*4*3*I
LIN**BP*BUYER-SKU-001*VP*VEND-SKU-001
SN1**8*EA
HL*5*3*I
LIN**BP*BUYER-SKU-002*VP*VEND-SKU-002
SN1**5*EA
CTT*2
SE*18*0001
GE*1*0002
IEA*1*000000002
EDI;
}

// ─── 850 Parse Tests ──────────────────────────────────────────────────────────

describe('EdiService - parse850', function () {
    test('parses PO number and date', function () {
        $svc    = new EdiService();
        $result = $svc->parse850(sample850());

        expect($result['type'])->toBe('850')
            ->and($result['po_number'])->toBe('PO12345')
            ->and($result['po_date'])->toBe('2024-01-01');
    });

    test('parses currency', function () {
        $result = (new EdiService())->parse850(sample850());
        expect($result['currency'])->toBe('USD');
    });

    test('parses line items with quantity and price', function () {
        $result = (new EdiService())->parse850(sample850());

        expect($result['items'])->toHaveCount(2);
        expect($result['items'][0]['quantity'])->toBe(10.0);
        expect($result['items'][0]['unit_price'])->toBe(25.0);
        expect($result['items'][0]['vendor_sku'])->toBe('VEND-SKU-001');
        expect($result['items'][0]['buyer_sku'])->toBe('BUYER-SKU-001');
    });

    test('parses bill-to address', function () {
        $result = (new EdiService())->parse850(sample850());
        expect($result['bill_to']['name'])->toBe('ACME CORP');
    });
});

// ─── 856 Parse Tests ──────────────────────────────────────────────────────────

describe('EdiService - parse856', function () {
    test('parses shipment id and ship date', function () {
        $result = (new EdiService())->parse856(sample856());

        expect($result['type'])->toBe('856')
            ->and($result['shipment_id'])->toBe('SHIP-001')
            ->and($result['ship_date'])->toBe('2024-01-05');
    });

    test('parses carrier and tracking number', function () {
        $result = (new EdiService())->parse856(sample856());
        expect($result['carrier'])->toBe('FEDEX')
            ->and($result['tracking_number'])->toBe('TRACK123456');
    });

    test('parses PO reference', function () {
        $result = (new EdiService())->parse856(sample856());
        expect($result['po_number'])->toBe('PO12345');
    });

    test('parses packages with items', function () {
        $result = (new EdiService())->parse856(sample856());
        expect($result['packages'])->toHaveCount(1);
        expect($result['packages'][0]['items'])->toHaveCount(2);
        expect($result['packages'][0]['items'][0]['quantity_shipped'])->toBe(8.0);
    });
});

// ─── 810 Generate Tests ───────────────────────────────────────────────────────

describe('EdiService - generate810', function () {
    function invoiceData(): array
    {
        return [
            'invoice_number' => 'INV-2024-001',
            'invoice_date'   => '2024-01-10',
            'po_number'      => 'PO12345',
            'currency'       => 'USD',
            'sender_id'      => 'WIDEHALO',
            'receiver_id'    => 'PARTNER',
            'sender_gs'      => 'WIDEHALO',
            'receiver_gs'    => 'PARTNER',
            'total_amount'   => 325.00,
            'tax_amount'     => 26.00,
            'freight_amount' => 15.00,
            'bill_from'      => ['name' => 'WideHalo Inc', 'address' => '1 ERP Way', 'city' => 'Paris', 'state' => '', 'zip' => '75001', 'country' => 'FR'],
            'bill_to'        => ['name' => 'ACME CORP', 'address' => '123 MAIN ST', 'city' => 'NEW YORK', 'state' => 'NY', 'zip' => '10001', 'country' => 'US'],
            'items'          => [
                ['line_number' => 1, 'sku' => 'BUYER-SKU-001', 'description' => 'Widget A', 'quantity' => 10, 'unit' => 'EA', 'unit_price' => 25.00],
                ['line_number' => 2, 'sku' => 'BUYER-SKU-002', 'description' => 'Widget B', 'quantity' => 5, 'unit' => 'EA', 'unit_price' => 50.00],
            ],
        ];
    }

    test('generates valid ISA header', function () {
        $edi = (new EdiService())->generate810(invoiceData());
        expect($edi)->toContain('ISA*')
            ->and($edi)->toContain('*ZZ*');
    });

    test('generates ST*810 segment', function () {
        $edi = (new EdiService())->generate810(invoiceData());
        expect($edi)->toContain('ST*810*');
    });

    test('generates BIG segment with invoice number', function () {
        $edi = (new EdiService())->generate810(invoiceData());
        expect($edi)->toContain('INV-2024-001');
    });

    test('generates IT1 line items', function () {
        $edi = (new EdiService())->generate810(invoiceData());
        expect($edi)->toContain('IT1*1*10.00*EA*25.00')
            ->and($edi)->toContain('IT1*2*5.00*EA*50.00');
    });

    test('generates TDS total', function () {
        $edi = (new EdiService())->generate810(invoiceData());
        expect($edi)->toContain('TDS*32500'); // 325.00 * 100
    });

    test('generates CTT and SE/GE/IEA trailers', function () {
        $edi = (new EdiService())->generate810(invoiceData());
        expect($edi)->toContain('CTT*2')
            ->and($edi)->toContain('SE*')
            ->and($edi)->toContain('GE*')
            ->and($edi)->toContain('IEA*');
    });
});

// ─── Transaction Log Tests ───────────────────────────────────────────────────

describe('EdiController - transaction logging', function () {
    beforeEach(fn () => actingAsUser('admin'));

    test('receive 850 logs transaction', function () {
        $user = actingAsUser('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/inventory/edi/receive', [
                'content'    => sample850(),
                'partner_id' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['type' => '850', 'status' => 'processed']);

        $this->assertDatabaseHas('edi_transactions', [
            'type'      => '850',
            'direction' => 'inbound',
            'status'    => 'processed',
        ]);
    });

    test('receive 856 logs transaction', function () {
        $user = actingAsUser('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/inventory/edi/receive', [
                'content' => sample856(),
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['type' => '856']);
    });

    test('generate 810 logs outbound transaction', function () {
        $user = actingAsUser('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/inventory/edi/generate-810', [
                'invoice_number' => 'INV-TEST-001',
                'total_amount'   => 100.00,
                'items' => [
                    ['sku' => 'SKU-A', 'quantity' => 2, 'unit_price' => 50.00],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['edi']);

        $this->assertDatabaseHas('edi_transactions', [
            'type'      => '810',
            'direction' => 'outbound',
        ]);
    });

    test('list edi transactions', function () {
        $user = actingAsUser('admin');

        EdiTransaction::factory()->count(3)->create(['type' => '850', 'direction' => 'inbound', 'status' => 'processed', 'content_raw' => 'x', 'occurred_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/inventory/edi/transactions');

        $response->assertStatus(200);
    });

    test('detect transaction set type', function () {
        $svc = new EdiService();
        expect($svc->detectTransactionSet(sample850()))->toBe('850')
            ->and($svc->detectTransactionSet(sample856()))->toBe('856');
    });
});
