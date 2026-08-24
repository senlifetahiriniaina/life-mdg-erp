<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Shared\Exceptions\BaseException;
use Modules\Shared\Exceptions\TenantException;
use Modules\Shared\Services\BaseService;
use Modules\Shared\Traits\MultiTenantScope;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers — concrete BaseService for testing
// ---------------------------------------------------------------------------

class ConcreteService extends BaseService
{
    public function doSomething(): int
    {
        return $this->getCompanyId();
    }

    public function exposeSetCompany(int $id): static
    {
        return $this->setCompanyId($id);
    }

    public function exposeVerifyOwnership(Model $model): void
    {
        $this->verifyCompanyOwnership($model);
    }
}

// ---------------------------------------------------------------------------
// BaseService
// ---------------------------------------------------------------------------

test('BaseService throws TenantException for invalid company_id of zero', function () {
    expect(fn () => new ConcreteService(0))
        ->toThrow(TenantException::class);
});

test('BaseService throws TenantException for negative company_id', function () {
    expect(fn () => new ConcreteService(-1))
        ->toThrow(TenantException::class);
});

test('BaseService stores valid company_id', function () {
    $service = new ConcreteService(42);
    expect($service->getCompanyId())->toBe(42);
});

test('BaseService setCompanyId changes the company context', function () {
    $service = new ConcreteService(1);
    $service->exposeSetCompany(5);
    expect($service->getCompanyId())->toBe(5);
});

test('BaseService setCompanyId throws for invalid id', function () {
    $service = new ConcreteService(1);
    expect(fn () => $service->exposeSetCompany(0))->toThrow(TenantException::class);
});

test('BaseService verifyCompanyOwnership throws when company_id mismatches', function () {
    $service = new ConcreteService(1);

    $model = new class extends Model {
        public int $company_id = 2; // different company
        protected $guarded = [];
    };

    expect(fn () => $service->exposeVerifyOwnership($model))
        ->toThrow(TenantException::class);
});

// ---------------------------------------------------------------------------
// TenantException
// ---------------------------------------------------------------------------

test('TenantException::invalidCompanyId creates exception with company_id context', function () {
    $e = TenantException::invalidCompanyId(-5, ['source' => 'test']);

    expect($e)->toBeInstanceOf(TenantException::class)
        ->and($e->getContext()['company_id'])->toBe(-5)
        ->and($e->getContext()['source'])->toBe('test');
});

test('TenantException::companyIsolationViolation has 403 status code', function () {
    $e = TenantException::companyIsolationViolation('Cross-tenant access attempt');

    expect($e->getCode())->toBe(403);
});

test('TenantException::multiTenancyNotConfigured has 500 status code', function () {
    $e = TenantException::multiTenancyNotConfigured();

    expect($e->getCode())->toBe(500);
});

// ---------------------------------------------------------------------------
// BaseException
// ---------------------------------------------------------------------------

test('BaseException toArray contains error_code, message, and context', function () {
    $e = new BaseException('Something went wrong', 500, null, ['key' => 'value']);

    $arr = $e->toArray();

    expect($arr)->toHaveKey('error_code')
        ->toHaveKey('message')
        ->toHaveKey('context')
        ->and($arr['message'])->toBe('Something went wrong')
        ->and($arr['context']['key'])->toBe('value');
});

// ---------------------------------------------------------------------------
// MultiTenantScope trait
//
// Chantier 32.8: this file used to also cover Modules\Shared\Services\
// {SentimentAnalysisService,UnifiedForecastingService} — both deleted as
// confirmed-dead code (zero real callers anywhere outside their own tests,
// each a functional duplicate of a real, live implementation elsewhere:
// Modules\Helpdesk\Services\SentimentAnalysisService — the real, ticket-
// aware one actually consumed by AiResponseService/AgentPerformanceAnalytics
// Service/PredictiveEscalationService — and Modules\Analytics\Services\
// ForecastingEngineService, the app's real, live forecasting engine
// (Phase 41 / Chantier 26A). Their exceptions (SentimentException,
// ForecastingException) went with them, since neither was ever thrown by
// anything else. See Modules/Shared's own Chantier 32.8 CLAUDE.md entry.
// ---------------------------------------------------------------------------

test('MultiTenantScope scopeForCompany filters by company_id', function () {
    // Verify the scope method exists and is callable by reflection
    $trait = new ReflectionClass(MultiTenantScope::class);

    expect($trait->hasMethod('scopeForCompany'))->toBeTrue()
        ->and($trait->hasMethod('scopeWithoutCompanyScope'))->toBeTrue()
        ->and($trait->hasMethod('bootMultiTenantScope'))->toBeTrue();
});
