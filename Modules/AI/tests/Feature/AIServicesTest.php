<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\AI\Services\AutomatedInsightsService;
use Modules\AI\Services\NaturalLanguageProcessingService;
use Modules\AI\Services\PredictiveAnalyticsService;
use Modules\AI\Services\RecommendationEngineService;

// ---------------------------------------------------------------------------
// AutomatedInsightsService
// ---------------------------------------------------------------------------

test('AutomatedInsightsService can be instantiated', function () {
    $service = new AutomatedInsightsService();
    expect($service)->toBeInstanceOf(AutomatedInsightsService::class);
});

test('generateInsights returns array with required keys', function () {
    $service = new AutomatedInsightsService();
    $data = [
        ['value' => 100], ['value' => 200], ['value' => 150],
    ];

    $result = $service->generateInsights($data, 'general');

    expect($result)->toBeArray()
        ->toHaveKey('insight_id')
        ->toHaveKey('context')
        ->toHaveKey('summary')
        ->toHaveKey('key_findings_count')
        ->toHaveKey('anomalies_count');
});

test('generateInsights returns correct context', function () {
    $service = new AutomatedInsightsService();
    $result = $service->generateInsights([['value' => 50]], 'sales');

    expect($result['context'])->toBe('sales');
});

test('generateInsights with empty data returns zero findings', function () {
    $service = new AutomatedInsightsService();
    $result = $service->generateInsights([], 'general');

    expect($result['key_findings_count'])->toBe(0);
});

test('getInsightDetails retrieves cached insight', function () {
    $service = new AutomatedInsightsService();
    $data = [['value' => 10], ['value' => 20], ['value' => 15]];

    $result = $service->generateInsights($data, 'performance');
    $details = $service->getInsightDetails($result['insight_id']);

    expect($details)->toBeArray()
        ->toHaveKey('summary')
        ->toHaveKey('key_findings')
        ->toHaveKey('anomalies')
        ->toHaveKey('trends');
});

test('getInsightDetails returns null for non-existent insight', function () {
    $service = new AutomatedInsightsService();

    expect($service->getInsightDetails('non_existent_id'))->toBeNull();
});

test('compareInsights returns error when insight not found', function () {
    $service = new AutomatedInsightsService();

    $result = $service->compareInsights('fake_id_1', 'fake_id_2');

    expect($result)->toHaveKey('error');
});

test('compareInsights compares two stored insights', function () {
    $service = new AutomatedInsightsService();
    $data1 = [['value' => 10], ['value' => 20]];
    $data2 = [['value' => 100], ['value' => 200], ['value' => 50]];

    $r1 = $service->generateInsights($data1, 'general');
    $r2 = $service->generateInsights($data2, 'sales');

    $comparison = $service->compareInsights($r1['insight_id'], $r2['insight_id']);

    expect($comparison)->toHaveKey('insight1_id')
        ->toHaveKey('insight2_id')
        ->toHaveKey('comparison');
});

test('getInsightsByPeriod returns period and insights structure', function () {
    $service = new AutomatedInsightsService();

    $result = $service->getInsightsByPeriod('2026-01-01', '2026-06-30');

    expect($result)->toHaveKey('period')
        ->toHaveKey('insights_count')
        ->toHaveKey('insights');
});

test('exportInsightReport returns error for unknown insight', function () {
    $service = new AutomatedInsightsService();

    $result = $service->exportInsightReport('unknown_insight', 'json');

    expect($result)->toHaveKey('error');
});

test('exportInsightReport json format contains valid JSON content', function () {
    $service = new AutomatedInsightsService();
    $data = [['value' => 5], ['value' => 15], ['value' => 10]];

    $r = $service->generateInsights($data, 'general');
    $report = $service->exportInsightReport($r['insight_id'], 'json');

    expect($report['format'])->toBe('json')
        ->and(json_decode($report['content'], true))->toBeArray();
});

test('exportInsightReport text format contains report header', function () {
    $service = new AutomatedInsightsService();
    $data = [['value' => 1], ['value' => 2], ['value' => 3]];

    $r = $service->generateInsights($data, 'general');
    $report = $service->exportInsightReport($r['insight_id'], 'text');

    expect($report['content'])->toContain('AUTOMATED INSIGHTS REPORT');
});

test('detectAnomalies with fewer than 3 data points returns empty', function () {
    $service = new AutomatedInsightsService();

    // Only 2 data points — anomaly detection requires at least 3
    $result = $service->generateInsights([['value' => 5], ['value' => 10]], 'general');
    $details = $service->getInsightDetails($result['insight_id']);

    expect($details['anomalies'])->toBeArray();
});

// ---------------------------------------------------------------------------
// NaturalLanguageProcessingService
// ---------------------------------------------------------------------------

test('NaturalLanguageProcessingService can be instantiated', function () {
    $service = new NaturalLanguageProcessingService();
    expect($service)->toBeInstanceOf(NaturalLanguageProcessingService::class);
});

test('extractIntent returns intent and entities', function () {
    $service = new NaturalLanguageProcessingService();

    $result = $service->extractIntent('Please create a new report for the last 30 days');

    expect($result)->toHaveKey('intent')
        ->toHaveKey('confidence')
        ->toHaveKey('entities')
        ->and($result['intent'])->toBe('creation');
});

test('analyzeSentiment positive text returns positive sentiment', function () {
    $service = new NaturalLanguageProcessingService();

    $result = $service->analyzeSentiment('This product is excellent and amazing!');

    expect($result['sentiment'])->toBe('positive')
        ->and($result)->toHaveKey('score')
        ->and($result)->toHaveKey('positive_indicators');
});

test('analyzeSentiment negative text returns negative sentiment', function () {
    $service = new NaturalLanguageProcessingService();

    $result = $service->analyzeSentiment('This is terrible and horrible');

    expect($result['sentiment'])->toBe('negative');
});

test('summarizeText returns compression ratio and summary', function () {
    $service = new NaturalLanguageProcessingService();
    $text = 'The quick brown fox jumps over the lazy dog. This sentence is a classic example. It contains all letters of the alphabet.';

    $result = $service->summarizeText($text, 2);

    expect($result)->toHaveKey('summary')
        ->toHaveKey('original_length')
        ->toHaveKey('compression_ratio')
        ->and($result['original_length'])->toBeGreaterThan(0);
});

test('extractKeywords returns keyword list with frequency', function () {
    $service = new NaturalLanguageProcessingService();

    $result = $service->extractKeywords('machine learning artificial intelligence data science machine learning');

    expect($result)->toHaveKey('keywords')
        ->toHaveKey('total_words')
        ->and($result['keywords'])->toBeArray();
});

test('classifyCategory returns category and scores', function () {
    $service = new NaturalLanguageProcessingService();

    $result = $service->classifyCategory('There is a bug in the database server, please help fix the error');

    expect($result)->toHaveKey('category')
        ->toHaveKey('confidence')
        ->toHaveKey('all_scores');
});

test('getCacheStatistics returns cache config', function () {
    $service = new NaturalLanguageProcessingService();

    $stats = $service->getCacheStatistics();

    expect($stats)->toHaveKey('cache_service')
        ->toHaveKey('ttl')
        ->toHaveKey('timestamp')
        ->and($stats['ttl'])->toBe(NaturalLanguageProcessingService::CACHE_TTL);
});

// ---------------------------------------------------------------------------
// PredictiveAnalyticsService
// ---------------------------------------------------------------------------

test('PredictiveAnalyticsService can be instantiated', function () {
    $service = new PredictiveAnalyticsService();
    expect($service)->toBeInstanceOf(PredictiveAnalyticsService::class);
});

test('buildModel returns model_id and trained status', function () {
    $service = new PredictiveAnalyticsService();

    $trainingData = array_map(fn ($i) => ['x' => $i, 'y' => $i * 2], range(1, 10));
    $result = $service->buildModel('revenue_forecast', $trainingData, ['type' => 'regression']);

    expect($result)->toHaveKey('model_id')
        ->toHaveKey('status')
        ->and($result['status'])->toBe('trained');
});

test('predict returns prediction for known model', function () {
    $service = new PredictiveAnalyticsService();

    $trainingData = array_map(fn ($i) => ['val' => $i], range(1, 10));
    $model = $service->buildModel('test_model', $trainingData);

    $prediction = $service->predict($model['model_id'], ['feature1' => 10, 'feature2' => 20]);

    expect($prediction)->toHaveKey('prediction')
        ->toHaveKey('confidence')
        ->and($prediction['prediction'])->toBeFloat();
});

test('predict returns error for unknown model', function () {
    $service = new PredictiveAnalyticsService();

    $result = $service->predict('non_existent_model', ['x' => 1]);

    expect($result)->toHaveKey('error');
});

test('forecast returns forecast periods array', function () {
    $service = new PredictiveAnalyticsService();
    $trainingData = array_map(fn ($i) => ['val' => $i], range(1, 10));
    $model = $service->buildModel('forecast_model', $trainingData);

    $result = $service->forecast($model['model_id'], 5);

    expect($result)->toHaveKey('forecast')
        ->and($result['forecast'])->toHaveCount(5)
        ->and($result['forecast_periods'])->toBe(5);
});

test('detectAnomalies on model data returns anomaly count', function () {
    $service = new PredictiveAnalyticsService();
    $trainingData = array_map(fn ($i) => ['v' => $i], range(1, 5));
    $model = $service->buildModel('anomaly_model', $trainingData);

    $data = [10, 12, 11, 200, 13, 10]; // 200 should be detected as anomaly
    $result = $service->detectAnomalies($model['model_id'], $data);

    expect($result)->toHaveKey('anomalies_detected')
        ->toHaveKey('anomalies')
        ->and($result['anomalies'])->toBeArray();
});

test('getModelPerformance returns accuracy metrics', function () {
    $service = new PredictiveAnalyticsService();
    $trainingData = array_map(fn ($i) => ['v' => $i], range(1, 5));
    $model = $service->buildModel('perf_model', $trainingData);

    $perf = $service->getModelPerformance($model['model_id']);

    expect($perf)->toHaveKey('accuracy')
        ->toHaveKey('precision')
        ->toHaveKey('recall')
        ->toHaveKey('f1_score');
});

test('retrainModel increases training samples', function () {
    $service = new PredictiveAnalyticsService();
    $trainingData = array_map(fn ($i) => ['v' => $i], range(1, 10));
    $model = $service->buildModel('retrain_model', $trainingData);

    $newData = array_map(fn ($i) => ['v' => $i], range(11, 15));
    $result = $service->retrainModel($model['model_id'], $newData);

    expect($result['status'])->toBe('retrained')
        ->and($result['total_training_samples'])->toBe(15);
});

// ---------------------------------------------------------------------------
// RecommendationEngineService
// ---------------------------------------------------------------------------

test('RecommendationEngineService can be instantiated', function () {
    $service = new RecommendationEngineService();
    expect($service)->toBeInstanceOf(RecommendationEngineService::class);
});

test('generateRecommendations returns recommendation_id and count', function () {
    $service = new RecommendationEngineService();

    $result = $service->generateRecommendations(1, 'products');

    expect($result)->toHaveKey('recommendation_id')
        ->toHaveKey('user_id')
        ->toHaveKey('count')
        ->toHaveKey('recommendations')
        ->and($result['user_id'])->toBe(1);
});

test('generateRecommendations count does not exceed MAX_RECOMMENDATIONS', function () {
    $service = new RecommendationEngineService();

    $result = $service->generateRecommendations(2, 'services');

    expect($result['count'])->toBeLessThanOrEqual(RecommendationEngineService::MAX_RECOMMENDATIONS);
});

test('getRecommendationDetails returns stored recommendation', function () {
    $service = new RecommendationEngineService();

    $rec = $service->generateRecommendations(3, 'content');
    $details = $service->getRecommendationDetails($rec['recommendation_id']);

    expect($details)->toBeArray()
        ->toHaveKey('user_id')
        ->toHaveKey('category')
        ->toHaveKey('recommendations');
});

test('getRecommendationDetails returns null for unknown id', function () {
    $service = new RecommendationEngineService();

    expect($service->getRecommendationDetails('no_such_id'))->toBeNull();
});

test('rateRecommendation records rating successfully', function () {
    $service = new RecommendationEngineService();

    $rec = $service->generateRecommendations(4, 'products');
    $rating = $service->rateRecommendation($rec['recommendation_id'], 5, 'Very helpful');

    expect($rating['status'])->toBe('recorded')
        ->and($rating['rating'])->toBe(5);
});

test('rateRecommendation returns error for unknown recommendation', function () {
    $service = new RecommendationEngineService();

    $result = $service->rateRecommendation('unknown_rec', 3);

    expect($result)->toHaveKey('error');
});

test('personalizeRecommendations updates user preferences', function () {
    $service = new RecommendationEngineService();

    $result = $service->personalizeRecommendations(5, ['price_range' => ['high'], 'categories' => ['enterprise']]);

    expect($result['status'])->toBe('personalized')
        ->and($result['user_id'])->toBe(5)
        ->and($result['preferences_updated'])->toBeGreaterThan(0);
});
