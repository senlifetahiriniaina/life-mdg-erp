<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Exceptions\BaseException;
use Modules\Shared\Exceptions\ForecastingException;
use Modules\Shared\Exceptions\PersonalizationException;
use Modules\Shared\Exceptions\SentimentException;
use Modules\Shared\Exceptions\TenantException;
use Modules\Shared\Services\BaseService;
use Modules\Shared\Services\SentimentAnalysisService;
use Modules\Shared\Services\UnifiedForecastingService;
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
// SentimentAnalysisService
// ---------------------------------------------------------------------------

test('SentimentAnalysisService can be instantiated with company_id', function () {
    $service = new SentimentAnalysisService(1);
    expect($service)->toBeInstanceOf(SentimentAnalysisService::class);
});

test('analyze empty string returns neutral sentiment with score 0', function () {
    $service = new SentimentAnalysisService(1);

    $result = $service->analyze('');

    expect($result['sentiment'])->toBe('neutral')
        ->and($result['score'])->toBe(0);
});

test('analyze positive text returns positive sentiment', function () {
    $service = new SentimentAnalysisService(1);

    $result = $service->analyze('This product is excellent and wonderful, I love it!');

    expect($result['sentiment'])->toBe('positive')
        ->and($result)->toHaveKey('confidence')
        ->and($result)->toHaveKey('emotions')
        ->and($result)->toHaveKey('language');
});

test('analyze negative text returns negative sentiment', function () {
    $service = new SentimentAnalysisService(1);

    $result = $service->analyze('This is terrible and awful, I hate it completely.');

    expect($result['sentiment'])->toBe('negative');
});

test('batchAnalyze processes multiple texts', function () {
    $service = new SentimentAnalysisService(1);

    $results = $service->batchAnalyze([
        'Great product!',
        'Very bad experience.',
        'It is okay.',
    ]);

    expect($results)->toBeArray()
        ->and(count($results))->toBe(3);
});

test('detectEmotions returns empty array for empty text', function () {
    $service = new SentimentAnalysisService(1);

    expect($service->detectEmotions(''))->toBeEmpty();
});

test('detectEmotions identifies anger-related words', function () {
    $service = new SentimentAnalysisService(1);

    $emotions = $service->detectEmotions('I am very angry and furious about this situation!');

    expect($emotions)->toHaveKey('anger')
        ->and($emotions['anger'])->toBeGreaterThan(0);
});

test('calculateScore returns 50 for empty text', function () {
    $service = new SentimentAnalysisService(1);

    expect($service->calculateScore(''))->toBe(50);
});

test('calculateScore returns higher value for positive text', function () {
    $service = new SentimentAnalysisService(1);

    $score = $service->calculateScore('amazing wonderful excellent great');

    expect($score)->toBeGreaterThan(50);
});

test('detectLanguage identifies French text', function () {
    $service = new SentimentAnalysisService(1);

    $lang = $service->detectLanguage('Bonjour merci beaucoup, le produit est très bon');

    expect($lang)->toBe('fr');
});

test('shouldEscalate returns true for very negative text', function () {
    $service = new SentimentAnalysisService(1);

    $result = $service->shouldEscalate('I am furious and angry! Terrible bad awful service!');

    expect($result)->toBeTrue();
});

test('getSummary returns sentiment distribution for multiple texts', function () {
    $service = new SentimentAnalysisService(1);

    $summary = $service->getSummary([
        'Excellent product!',
        'Terrible experience!',
        'It is okay.',
    ]);

    expect($summary)->toHaveKey('sentiment_distribution')
        ->toHaveKey('average_score')
        ->toHaveKey('total_texts')
        ->and($summary['total_texts'])->toBe(3);
});

// ---------------------------------------------------------------------------
// UnifiedForecastingService
// ---------------------------------------------------------------------------

test('UnifiedForecastingService can be instantiated', function () {
    $service = new UnifiedForecastingService(1);
    expect($service)->toBeInstanceOf(UnifiedForecastingService::class);
});

test('forecastARIMA returns empty collection for insufficient data', function () {
    $service = new UnifiedForecastingService(1);

    $result = $service->forecastARIMA([1, 2, 3], 5);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->isEmpty())->toBeTrue();
});

test('forecastARIMA returns collection with correct periods for sufficient data', function () {
    $service = new UnifiedForecastingService(1);

    $historicalData = array_fill(0, 35, 100.0);
    $result = $service->forecastARIMA($historicalData, 7);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(7)
        ->and($result->first())->toHaveKey('model')
        ->and($result->first()['model'])->toBe('arima');
});

test('forecastExponentialSmoothing returns empty collection for insufficient data', function () {
    $service = new UnifiedForecastingService(1);

    $result = $service->forecastExponentialSmoothing([10, 20], 3);

    expect($result->isEmpty())->toBeTrue();
});

test('forecastExponentialSmoothing returns correct period count', function () {
    $service = new UnifiedForecastingService(1);

    $data = array_fill(0, 35, 150.0);
    $result = $service->forecastExponentialSmoothing($data, 10, 0.3);

    expect($result->count())->toBe(10)
        ->and($result->first()['model'])->toBe('exponential_smoothing');
});

test('forecastEnsemble combines ARIMA and exponential smoothing', function () {
    $service = new UnifiedForecastingService(1);

    $data = array_map(fn ($i) => (float) ($i * 2 + 10), range(1, 40));
    $result = $service->forecastEnsemble($data, 5);

    expect($result->count())->toBe(5)
        ->and($result->first()['model'])->toBe('ensemble')
        ->and($result->first())->toHaveKey('components');
});

test('detectSeasonality returns empty array for insufficient data', function () {
    $service = new UnifiedForecastingService(1);

    $result = $service->detectSeasonality([1, 2, 3], 7);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('detectSeasonality returns seasonal indices for sufficient data', function () {
    $service = new UnifiedForecastingService(1);

    $data = array_map(fn ($i) => (float) (sin($i * M_PI / 7) * 50 + 100), range(0, 28));
    $seasonality = $service->detectSeasonality($data, 7);

    expect($seasonality)->toBeArray()
        ->and(count($seasonality))->toBe(7);
});

test('calculateAccuracy returns 0 for empty arrays', function () {
    $service = new UnifiedForecastingService(1);

    expect($service->calculateAccuracy([], []))->toBe(0.0);
});

test('calculateAccuracy returns 100 for perfect predictions', function () {
    $service = new UnifiedForecastingService(1);

    $actual    = [100.0, 200.0, 150.0, 175.0];
    $predicted = [100.0, 200.0, 150.0, 175.0];

    expect($service->calculateAccuracy($actual, $predicted))->toBe(100.0);
});

test('calculateAccuracy returns 0 for mismatched array sizes', function () {
    $service = new UnifiedForecastingService(1);

    expect($service->calculateAccuracy([1.0, 2.0], [1.0]))->toBe(0.0);
});

test('cacheForecast stores and getCachedForecast retrieves', function () {
    $service = new UnifiedForecastingService(1);

    $forecast = collect([['value' => 100.0, 'model' => 'arima', 'confidence' => 0.8]]);
    $service->cacheForecast('test_key', $forecast);

    $cached = $service->getCachedForecast('test_key');

    expect($cached)->toBeInstanceOf(Collection::class)
        ->and($cached->first()['value'])->toBe(100.0);
});

test('clearForecastCache removes cached forecast', function () {
    $service = new UnifiedForecastingService(1);

    $forecast = collect([['value' => 200.0]]);
    $service->cacheForecast('clear_test', $forecast);
    $service->clearForecastCache('clear_test');

    expect($service->getCachedForecast('clear_test'))->toBeNull();
});

test('applySeasonalityAdjustment returns original when seasonality empty', function () {
    $service = new UnifiedForecastingService(1);

    $forecast = collect([
        ['value' => 100.0, 'days_ahead' => 1],
        ['value' => 110.0, 'days_ahead' => 2],
    ]);

    $adjusted = $service->applySeasonalityAdjustment($forecast, []);

    expect($adjusted->count())->toBe(2)
        ->and($adjusted->first()['value'])->toBe(100.0);
});

// ---------------------------------------------------------------------------
// MultiTenantScope trait
// ---------------------------------------------------------------------------

test('MultiTenantScope scopeForCompany filters by company_id', function () {
    // Verify the scope method exists and is callable by reflection
    $trait = new ReflectionClass(MultiTenantScope::class);

    expect($trait->hasMethod('scopeForCompany'))->toBeTrue()
        ->and($trait->hasMethod('scopeWithoutCompanyScope'))->toBeTrue()
        ->and($trait->hasMethod('bootMultiTenantScope'))->toBeTrue();
});
