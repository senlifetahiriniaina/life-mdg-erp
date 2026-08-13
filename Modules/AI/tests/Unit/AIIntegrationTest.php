<?php

namespace Modules\AI\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\AI\Services\PredictiveAnalyticsService;
use Modules\AI\Services\RecommendationEngineService;
use Modules\AI\Services\NaturalLanguageProcessingService;
use Modules\AI\Services\AutomatedInsightsService;
use Tests\TestCase;

class AIIntegrationTest extends TestCase
{
    protected PredictiveAnalyticsService $analyticsService;
    protected RecommendationEngineService $recommendationService;
    protected NaturalLanguageProcessingService $nlpService;
    protected AutomatedInsightsService $insightsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyticsService = app(PredictiveAnalyticsService::class);
        $this->recommendationService = app(RecommendationEngineService::class);
        $this->nlpService = app(NaturalLanguageProcessingService::class);
        $this->insightsService = app(AutomatedInsightsService::class);

        Cache::flush();
    }

    // ======================================================================
    // Predictive Analytics Tests
    // ======================================================================

    /**
     * Test build model
     */
    public function test_build_predictive_model()
    {
        $trainingData = [
            ['features' => [1, 2], 'target' => 3],
            ['features' => [2, 3], 'target' => 5],
            ['features' => [3, 4], 'target' => 7],
        ];

        $result = $this->analyticsService->buildModel('SalesPredictor', $trainingData);

        $this->assertEquals('trained', $result['status']);
        $this->assertArrayHasKey('model_id', $result);
    }

    public function test_make_prediction()
    {
        $model = $this->analyticsService->buildModel('TestModel', []);
        $modelId = $model['model_id'];

        $result = $this->analyticsService->predict($modelId, ['feature1' => 10, 'feature2' => 20]);

        $this->assertArrayHasKey('prediction', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function test_forecast_values()
    {
        $model = $this->analyticsService->buildModel('ForecastModel', []);
        $modelId = $model['model_id'];

        $result = $this->analyticsService->forecast($modelId, 5);

        $this->assertEquals(5, count($result['forecast']));
    }

    public function test_detect_anomalies()
    {
        $model = $this->analyticsService->buildModel('AnomalyModel', []);
        $modelId = $model['model_id'];

        $data = [50, 55, 52, 53, 51, 100, 54, 52]; // 100 is anomaly

        $result = $this->analyticsService->detectAnomalies($modelId, $data);

        $this->assertGreaterThanOrEqual(0, count($result['anomalies_detected']));
    }

    public function test_get_model_performance()
    {
        $model = $this->analyticsService->buildModel('PerformanceModel', []);
        $modelId = $model['model_id'];

        $performance = $this->analyticsService->getModelPerformance($modelId);

        $this->assertArrayHasKey('accuracy', $performance);
        $this->assertArrayHasKey('precision', $performance);
    }

    public function test_retrain_model()
    {
        $model = $this->analyticsService->buildModel('RetrainModel', []);
        $modelId = $model['model_id'];

        $result = $this->analyticsService->retrainModel($modelId, [['data' => 'test']]);

        $this->assertEquals('retrained', $result['status']);
    }

    /**
     * Test list models
     */
    public function test_list_models()
    {
        $this->analyticsService->buildModel('Model1', []);
        $this->analyticsService->buildModel('Model2', []);

        $models = $this->analyticsService->listModels();

        $this->assertGreaterThan(0, count($models));
    }

    // ======================================================================
    // Recommendation Engine Tests
    // ======================================================================

    /**
     * Test generate recommendations
     */
    public function test_generate_recommendations()
    {
        $result = $this->recommendationService->generateRecommendations(1, 'products');

        $this->assertEquals(1, $result['user_id']);
        $this->assertArrayHasKey('count', $result);
    }

    public function test_rate_recommendation()
    {
        $rec = $this->recommendationService->generateRecommendations(1, 'products');
        $recId = $rec['recommendation_id'];

        $result = $this->recommendationService->rateRecommendation($recId, 5, 'Very helpful');

        $this->assertEquals('recorded', $result['status']);
    }

    public function test_get_recommendation_effectiveness()
    {
        $rec = $this->recommendationService->generateRecommendations(1, 'products');
        $recId = $rec['recommendation_id'];

        $this->recommendationService->rateRecommendation($recId, 5);

        $effectiveness = $this->recommendationService->getRecommendationEffectiveness(1);

        $this->assertArrayHasKey('effectiveness_score', $effectiveness);
    }

    public function test_personalize_recommendations()
    {
        $result = $this->recommendationService->personalizeRecommendations(1, [
            'price_range' => ['high'],
            'brand_preference' => ['luxury'],
        ]);

        $this->assertEquals('personalized', $result['status']);
    }

    /**
     * Test get recommendation details
     */
    public function test_get_recommendation_details()
    {
        $rec = $this->recommendationService->generateRecommendations(1, 'products');
        $recId = $rec['recommendation_id'];

        $details = $this->recommendationService->getRecommendationDetails($recId);

        $this->assertNotNull($details);
        $this->assertEquals('products', $details['category']);
    }

    // ======================================================================
    // NLP Tests
    // ======================================================================

    /**
     * Test extract intent
     */
    public function test_extract_intent()
    {
        $result = $this->nlpService->extractIntent('Create a new user account');

        $this->assertArrayHasKey('intent', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function test_sentiment_analysis()
    {
        $result = $this->nlpService->analyzeSentiment('This product is amazing and wonderful!');

        $this->assertArrayHasKey('sentiment', $result);
        $this->assertArrayHasKey('score', $result);
    }

    public function test_summarize_text()
    {
        $text = "This is a long text. It contains multiple sentences. Each sentence has information. " .
                "We want to summarize it. The summary should be shorter. But still contain key information.";

        $result = $this->nlpService->summarizeText($text, 3);

        $this->assertArrayHasKey('summary', $result);
        $this->assertLessThan(strlen($text), strlen($result['summary']));
    }

    public function test_extract_keywords()
    {
        $text = "Machine learning is a subset of artificial intelligence. It focuses on data analysis and predictions.";

        $result = $this->nlpService->extractKeywords($text, 5);

        $this->assertGreaterThan(0, count($result['keywords']));
    }

    public function test_classify_category()
    {
        $result = $this->nlpService->classifyCategory('What is the API endpoint for users database?');

        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    /**
     * Test detect language
     */
    public function test_detect_language()
    {
        $result = $this->nlpService->detectLanguage('The quick brown fox jumps over the lazy dog');

        $this->assertArrayHasKey('detected_language', $result);
    }

    // ======================================================================
    // Automated Insights Tests
    // ======================================================================

    /**
     * Test generate insights
     */
    public function test_generate_insights()
    {
        $data = [
            ['value' => 100],
            ['value' => 105],
            ['value' => 102],
            ['value' => 108],
        ];

        $result = $this->insightsService->generateInsights($data, 'performance');

        $this->assertArrayHasKey('insight_id', $result);
        $this->assertEquals('performance', $result['context']);
    }

    public function test_get_insight_details()
    {
        $data = [['value' => 100], ['value' => 105]];

        $insight = $this->insightsService->generateInsights($data);
        $insightId = $insight['insight_id'];

        $details = $this->insightsService->getInsightDetails($insightId);

        $this->assertNotNull($details);
        $this->assertArrayHasKey('summary', $details);
    }

    public function test_compare_insights()
    {
        $data1 = [['value' => 100]];
        $data2 = [['value' => 200]];

        $insight1 = $this->insightsService->generateInsights($data1);
        $insight2 = $this->insightsService->generateInsights($data2);

        $comparison = $this->insightsService->compareInsights(
            $insight1['insight_id'],
            $insight2['insight_id']
        );

        $this->assertArrayHasKey('comparison', $comparison);
    }

    public function test_get_insights_by_period()
    {
        $result = $this->insightsService->getInsightsByPeriod('2026-01-01', '2026-01-31');

        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('insights', $result);
    }

    /**
     * Test export insight report
     */
    public function test_export_insight_report()
    {
        $data = [['value' => 100]];

        $insight = $this->insightsService->generateInsights($data);
        $insightId = $insight['insight_id'];

        $report = $this->insightsService->exportInsightReport($insightId, 'json');

        $this->assertArrayHasKey('content', $report);
    }
}
