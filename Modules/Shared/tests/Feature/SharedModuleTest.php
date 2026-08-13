<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Exceptions\BaseException;
use Modules\Shared\Exceptions\ForecastingException;
use Modules\Shared\Exceptions\PersonalizationException;
use Modules\Shared\Exceptions\SentimentException;
use Modules\Shared\Exceptions\TenantException;
use Modules\Shared\Exceptions\ValidationException;
use Modules\Shared\Jobs\BaseAsyncJob;
use Modules\Shared\Services\BaseService;
use Modules\Shared\Services\SentimentAnalysisService;
use Modules\Shared\Services\UnifiedForecastingService;
use Modules\Shared\Traits\MultiTenantScope;

// ─── Concrete stubs for abstract classes ─────────────────────────────────────

class ModuleTestableService extends BaseService
{
    public function exposed_companyId(): int { return $this->companyId; }
    public function exposed_setCompanyId(int $id): static { return $this->setCompanyId($id); }
    public function exposed_verifyOwnership(Model $m): void { $this->verifyCompanyOwnership($m); }
}

// ─── BaseException ────────────────────────────────────────────────────────────

test('BaseException stores message and code', function () {
    $ex = new BaseException('Something went wrong', 500);

    expect($ex->getMessage())->toBe('Something went wrong')
        ->and($ex->getCode())->toBe(500);
});

test('BaseException getErrorCode returns UNKNOWN_ERROR by default', function () {
    $ex = new BaseException('test');

    expect($ex->getErrorCode())->toBe('UNKNOWN_ERROR');
});

test('BaseException toArray returns expected keys', function () {
    $ex = new BaseException('test', 400, null, ['key' => 'value']);

    $arr = $ex->toArray();
    expect($arr)->toHaveKey('error_code')
        ->toHaveKey('message')
        ->toHaveKey('context');
});

test('BaseException getContext returns constructor context', function () {
    $ex = new BaseException('test', 0, null, ['foo' => 'bar']);

    expect($ex->getContext())->toBe(['foo' => 'bar']);
});

// ─── TenantException ──────────────────────────────────────────────────────────

test('TenantException::invalidCompanyId creates exception with context', function () {
    $ex = TenantException::invalidCompanyId(0);

    expect($ex)->toBeInstanceOf(TenantException::class)
        ->and($ex->getCode())->toBe(400)
        ->and($ex->getContext()['company_id'])->toBe(0);
});

test('TenantException::companyIsolationViolation creates 403 exception', function () {
    $ex = TenantException::companyIsolationViolation('Cross-tenant access');

    expect($ex->getCode())->toBe(403)
        ->and($ex->getMessage())->toContain('Cross-tenant');
});

test('TenantException::multiTenancyNotConfigured creates 500 exception', function () {
    $ex = TenantException::multiTenancyNotConfigured();

    expect($ex->getCode())->toBe(500);
});

// ─── Specialist exceptions ────────────────────────────────────────────────────

test('ForecastingException class exists and extends BaseException', function () {
    expect(class_exists(ForecastingException::class))->toBeTrue();
    expect(new ForecastingException('test'))->toBeInstanceOf(BaseException::class);
});

test('PersonalizationException class exists', function () {
    expect(class_exists(PersonalizationException::class))->toBeTrue();
});

test('SentimentException class exists', function () {
    expect(class_exists(SentimentException::class))->toBeTrue();
});

test('ValidationException class exists', function () {
    expect(class_exists(ValidationException::class))->toBeTrue();
});

// ─── BaseService ──────────────────────────────────────────────────────────────

test('BaseService constructor rejects zero company_id', function () {
    expect(fn () => new ModuleTestableService(0))->toThrow(TenantException::class);
});

test('BaseService constructor rejects negative company_id', function () {
    expect(fn () => new ModuleTestableService(-5))->toThrow(TenantException::class);
});

test('BaseService stores and returns valid company_id', function () {
    $service = new ModuleTestableService(42);
    expect($service->getCompanyId())->toBe(42);
});

test('BaseService setCompanyId updates company_id', function () {
    $service = new ModuleTestableService(1);
    $service->exposed_setCompanyId(99);
    expect($service->getCompanyId())->toBe(99);
});

test('BaseService setCompanyId rejects invalid id', function () {
    $service = new ModuleTestableService(1);
    expect(fn () => $service->exposed_setCompanyId(0))->toThrow(TenantException::class);
});

// ─── BaseService verifyCompanyOwnership ──────────────────────────────────────

test('BaseService verifyCompanyOwnership throws when company mismatch', function () {
    $service = new ModuleTestableService(1);

    $model             = new class extends Model {};
    $model->company_id = 2; // different from service's companyId (1)

    expect(fn () => $service->exposed_verifyOwnership($model))
        ->toThrow(TenantException::class);
});

test('BaseService verifyCompanyOwnership passes when company matches', function () {
    $service = new ModuleTestableService(5);

    $model             = new class extends Model {};
    $model->company_id = 5;

    // Should not throw
    $service->exposed_verifyOwnership($model);
    expect(true)->toBeTrue();
});

// ─── SentimentAnalysisService ─────────────────────────────────────────────────

test('SentimentAnalysisService can be instantiated with valid company_id', function () {
    $service = new SentimentAnalysisService(1);
    expect($service)->toBeInstanceOf(SentimentAnalysisService::class);
});

test('SentimentAnalysisService rejects invalid company_id', function () {
    expect(fn () => new SentimentAnalysisService(0))->toThrow(TenantException::class);
});

test('SentimentAnalysisService analyze returns sentiment keys', function () {
    $service = new SentimentAnalysisService(1);
    $result  = $service->analyze('Great product, very happy!');

    expect($result)->toHaveKey('sentiment')
        ->toHaveKey('score');
});

test('SentimentAnalysisService analyze positive text returns positive sentiment', function () {
    $service = new SentimentAnalysisService(1);
    $result  = $service->analyze('Excellent service, I am very satisfied!');

    expect($result['sentiment'])->toBe('positive');
});

test('SentimentAnalysisService analyze empty text returns neutral', function () {
    $service = new SentimentAnalysisService(1);
    $result  = $service->analyze('');

    expect($result['sentiment'])->toBe('neutral')
        ->and($result['score'])->toBe(0);
});
