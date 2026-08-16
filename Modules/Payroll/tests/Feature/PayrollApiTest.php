<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Payroll API - Authentication', function () {
    it('rejects unauthenticated access to payslips', function () {
        $this->getJson('/api/v1/payroll/payslips')
            ->assertStatus(401);
    });

    it('rejects unauthenticated generate request', function () {
        $this->postJson('/api/v1/payroll/generate', [])
            ->assertStatus(401);
    });

    it('rejects unauthenticated statistics request', function () {
        $this->getJson('/api/v1/payroll/statistics')
            ->assertStatus(401);
    });
});

describe('Payroll API - Payslips', function () {
    beforeEach(function () {
        $this->user = actingAsUser('hr-manager');
    });

    it('returns payslips list', function () {
        $response = $this->getJson('/api/v1/payroll/payslips');
        $response->assertStatus(200);
    });

    it('filters payslips by employee', function () {
        $response = $this->getJson('/api/v1/payroll/payslips?employee_id=1');
        $response->assertStatus(200);
    });

    it('filters payslips by month', function () {
        $response = $this->getJson('/api/v1/payroll/payslips?month=2026-01');
        $response->assertStatus(200);
    });

    it('returns statistics', function () {
        $response = $this->getJson('/api/v1/payroll/statistics');
        $response->assertStatus(200);
    });

    it('returns taxes by country for Senegal', function () {
        $response = $this->getJson('/api/v1/payroll/taxes/by-country?country=SN');
        $response->assertStatus(200);
    });

    it('returns taxes by country for Ivory Coast', function () {
        $response = $this->getJson('/api/v1/payroll/taxes/by-country?country=CI');
        $response->assertStatus(200);
    });

    it('returns taxes by country for Cameroon', function () {
        $response = $this->getJson('/api/v1/payroll/taxes/by-country?country=CM');
        $response->assertStatus(200);
    });
});

describe('Payroll API - Generate', function () {
    beforeEach(function () {
        $this->user = actingAsUser('hr-manager');
    });

    it('validates required period field', function () {
        $response = $this->postJson('/api/v1/payroll/generate', []);
        $response->assertStatus(422);
    });

    it('validates period format is YYYY-MM', function () {
        $response = $this->postJson('/api/v1/payroll/generate', [
            'period' => 'invalid-format',
        ]);
        $response->assertStatus(422);
    });

    it('accepts valid period format', function () {
        $response = $this->postJson('/api/v1/payroll/generate', [
            'period' => '2026-01',
        ]);
        $response->assertStatus(201);
    });
});

describe('Payroll API - Batch Approve', function () {
    beforeEach(function () {
        $this->user = actingAsUser('hr-manager');
    });

    it('accepts batch approval with period', function () {
        $response = $this->postJson('/api/v1/payroll/payslips/approve-batch', [
            'period' => '2026-01',
        ]);
        $response->assertStatus(200)->assertJsonStructure(['approved_count']);
    });

    it('requires period field for approve-batch', function () {
        $response = $this->postJson('/api/v1/payroll/payslips/approve-batch', []);
        $response->assertStatus(422);
    });

    it('validates period format for approve-batch', function () {
        $response = $this->postJson('/api/v1/payroll/payslips/approve-batch', [
            'period' => 'janvier-2026',
        ]);
        $response->assertStatus(422);
    });
});

describe('Payroll API - Process Payment', function () {
    beforeEach(function () {
        $this->user = actingAsUser('hr-manager');
    });

    it('accepts payment processing request with period', function () {
        $response = $this->postJson('/api/v1/payroll/process-payment', [
            'period' => '2026-01',
        ]);
        $response->assertStatus(200)->assertJsonStructure(['paid_count']);
    });

    it('requires period for process-payment', function () {
        $response = $this->postJson('/api/v1/payroll/process-payment', []);
        $response->assertStatus(422);
    });

    it('validates period format for process-payment', function () {
        $response = $this->postJson('/api/v1/payroll/process-payment', [
            'period' => 'invalid',
        ]);
        $response->assertStatus(422);
    });
});
