<?php

use Carbon\Carbon;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeDocument;
use Modules\HR\Services\DocumentExpiryService;

// ── Helpers ────────────────────────────────────────────────────────────────

function makeEmployee(): Employee
{
    $dept = Department::factory()->create();
    return Employee::factory()->create(['department_id' => $dept->id]);
}

function makeDoc(Employee $employee, array $attrs = []): EmployeeDocument
{
    return EmployeeDocument::create(array_merge([
        'employee_id'       => $employee->id,
        'document_type'     => 'work_permit',
        'title'             => 'Work Permit',
        'status'            => 'valid',
        'alert_days_before' => 60,
        'alert_sent_60'     => false,
        'alert_sent_30'     => false,
        'alert_sent_7'      => false,
    ], $attrs));
}

// ── Tests ──────────────────────────────────────────────────────────────────

describe('EmployeeDocument model', function () {

    test('creates a document with all required fields', function () {
        $emp = makeEmployee();
        $doc = makeDoc($emp, ['expiry_date' => now()->addDays(90)->toDateString()]);

        expect($doc->id)->toBeInt()
            ->and($doc->document_type)->toBe('work_permit')
            ->and($doc->expiry_date)->toBeInstanceOf(Carbon::class);
    });

    test('days_until_expiry returns correct count', function () {
        $emp = makeEmployee();
        $doc = makeDoc($emp, ['expiry_date' => now()->addDays(45)->toDateString()]);

        expect($doc->days_until_expiry)->toBeGreaterThanOrEqual(44)
            ->and($doc->days_until_expiry)->toBeLessThanOrEqual(46);
    });

    test('days_until_expiry is negative for expired documents', function () {
        $emp = makeEmployee();
        $doc = makeDoc($emp, ['expiry_date' => now()->subDays(5)->toDateString()]);

        expect($doc->days_until_expiry)->toBeLessThan(0);
    });

    test('computeStatus returns expired when expiry_date is past', function () {
        $emp = makeEmployee();
        $doc = makeDoc($emp, ['expiry_date' => now()->subDay()->toDateString()]);

        expect($doc->computeStatus())->toBe('expired');
    });

    test('computeStatus returns expiring_soon within alert_days_before', function () {
        $emp = makeEmployee();
        $doc = makeDoc($emp, [
            'expiry_date'       => now()->addDays(30)->toDateString(),
            'alert_days_before' => 60,
        ]);

        expect($doc->computeStatus())->toBe('expiring_soon');
    });

    test('computeStatus returns valid when far from expiry', function () {
        $emp = makeEmployee();
        $doc = makeDoc($emp, [
            'expiry_date'       => now()->addDays(120)->toDateString(),
            'alert_days_before' => 60,
        ]);

        expect($doc->computeStatus())->toBe('valid');
    });

    test('computeStatus returns valid when no expiry date', function () {
        $emp = makeEmployee();
        $doc = makeDoc($emp);  // no expiry_date

        expect($doc->computeStatus())->toBe('valid');
    });
});

describe('DocumentExpiryService', function () {

    test('getExpiringDocuments returns docs within threshold', function () {
        $service = app(DocumentExpiryService::class);
        $emp     = makeEmployee();

        makeDoc($emp, ['expiry_date' => now()->addDays(15)->toDateString()]);  // in 15 days — ✓
        makeDoc($emp, ['expiry_date' => now()->addDays(60)->toDateString()]); // in 60 days — ✗ for 30-day window

        $expiring = $service->getExpiringDocuments(30);

        expect($expiring->count())->toBeGreaterThanOrEqual(1);
        foreach ($expiring as $doc) {
            expect($doc->expiry_date->isFuture())->toBeTrue()
                ->and($doc->expiry_date->diffInDays(now()))->toBeLessThanOrEqual(30);
        }
    });

    test('getExpiredDocuments returns past-expiry docs', function () {
        $service = app(DocumentExpiryService::class);
        $emp     = makeEmployee();

        makeDoc($emp, ['expiry_date' => now()->subDays(3)->toDateString()]);
        makeDoc($emp, ['expiry_date' => now()->addDays(10)->toDateString()]); // still valid

        $expired = $service->getExpiredDocuments();

        expect($expired->count())->toBeGreaterThanOrEqual(1);
        foreach ($expired as $doc) {
            expect($doc->expiry_date->isPast())->toBeTrue();
        }
    });

    test('markExpired updates status to expired', function () {
        $service = app(DocumentExpiryService::class);
        $emp     = makeEmployee();

        $doc = makeDoc($emp, [
            'expiry_date' => now()->subDays(2)->toDateString(),
            'status'      => 'valid',
        ]);

        $service->markExpired();

        expect($doc->fresh()->status)->toBe('expired');
    });

    test('refreshStatuses marks expiring_soon and expired correctly', function () {
        $service = app(DocumentExpiryService::class);
        $emp     = makeEmployee();

        $docExpired  = makeDoc($emp, ['expiry_date' => now()->subDay()->toDateString(), 'status' => 'valid']);
        $docSoon     = makeDoc($emp, ['expiry_date' => now()->addDays(20)->toDateString(), 'status' => 'valid', 'alert_days_before' => 60]);
        $docFar      = makeDoc($emp, ['expiry_date' => now()->addDays(120)->toDateString(), 'status' => 'valid']);

        $service->refreshStatuses();

        expect($docExpired->fresh()->status)->toBe('expired')
            ->and($docSoon->fresh()->status)->toBe('expiring_soon')
            ->and($docFar->fresh()->status)->toBe('valid');
    });

    test('complianceReport returns correct totals', function () {
        $service = app(DocumentExpiryService::class);
        $emp     = makeEmployee();

        makeDoc($emp, ['expiry_date' => now()->addDays(90)->toDateString(), 'status' => 'valid']);
        makeDoc($emp, ['expiry_date' => now()->subDay()->toDateString(), 'status' => 'expired']);

        $report = $service->complianceReport();

        expect($report)->toHaveKey('total')
            ->and($report)->toHaveKey('valid')
            ->and($report)->toHaveKey('expired')
            ->and($report)->toHaveKey('by_type')
            ->and($report)->toHaveKey('by_department')
            ->and($report['total'])->toBeGreaterThanOrEqual(2);
    });

    test('runDailyAlerts marks alert_sent flags', function () {
        $service = app(DocumentExpiryService::class);
        $emp     = makeEmployee();

        // Document expiring in exactly 7 days
        $doc = makeDoc($emp, [
            'expiry_date'   => now()->addDays(6)->toDateString(),
            'status'        => 'expiring_soon',
            'alert_sent_7'  => false,
            'alert_sent_30' => false,
            'alert_sent_60' => false,
        ]);

        $result = $service->runDailyAlerts();

        expect($result)->toHaveKey('sent')
            ->and($result)->toHaveKey('errors');

        // alert_sent_7 should now be true
        expect($doc->fresh()->alert_sent_7)->toBeTrue();
    });
});

describe('Document API endpoints', function () {

    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('GET /documents returns list', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/documents');

        $response->assertStatus(200);
    });

    test('POST /documents creates document', function () {
        $emp = makeEmployee();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/hr/documents', [
                'employee_id'   => $emp->id,
                'document_type' => 'work_permit',
                'title'         => 'Work Permit 2026',
                'expiry_date'   => now()->addYear()->toDateString(),
                'country'       => 'SN',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Work Permit 2026');

        $this->assertDatabaseHas('hr_employee_documents', [
            'employee_id'   => $emp->id,
            'document_type' => 'work_permit',
        ]);
    });

    test('GET /documents/expiring returns expiring documents', function () {
        $emp = makeEmployee();
        makeDoc($emp, ['expiry_date' => now()->addDays(10)->toDateString()]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/documents/expiring?days=30');

        $response->assertStatus(200)
            ->assertJsonStructure(['days', 'count', 'documents']);
    });

    test('GET /documents/compliance-report returns report structure', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/documents/compliance-report');

        $response->assertStatus(200)
            ->assertJsonStructure(['total', 'valid', 'expired', 'expiring_soon', 'by_type']);
    });

    test('POST /documents/{id}/remind triggers reminder', function () {
        $emp = makeEmployee();
        $doc = makeDoc($emp, ['expiry_date' => now()->addDays(15)->toDateString()]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/hr/documents/{$doc->id}/remind");

        $response->assertStatus(200);
    });
});
