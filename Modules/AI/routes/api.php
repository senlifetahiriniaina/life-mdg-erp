<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'session.security'])->prefix('v1/ai')->group(function () {
    // Predictive Analytics
    Route::post('analytics/models', 'Modules\AI\Http\Controllers\AnalyticsController@buildModel');
    Route::get('analytics/models', 'Modules\AI\Http\Controllers\AnalyticsController@listModels');
    Route::get('analytics/models/{modelId}', 'Modules\AI\Http\Controllers\AnalyticsController@getModel');
    Route::post('analytics/predict/{modelId}', 'Modules\AI\Http\Controllers\AnalyticsController@predict');
    Route::post('analytics/forecast/{modelId}', 'Modules\AI\Http\Controllers\AnalyticsController@forecast');
    Route::post('analytics/anomalies/{modelId}', 'Modules\AI\Http\Controllers\AnalyticsController@detectAnomalies');
    Route::get('analytics/performance/{modelId}', 'Modules\AI\Http\Controllers\AnalyticsController@getPerformance');
    Route::post('analytics/retrain/{modelId}', 'Modules\AI\Http\Controllers\AnalyticsController@retrainModel');

    // Recommendations
    Route::post('recommendations/generate', 'Modules\AI\Http\Controllers\RecommendationsController@generate');
    Route::get('recommendations/{recommendationId}', 'Modules\AI\Http\Controllers\RecommendationsController@getDetails');
    Route::post('recommendations/{recommendationId}/rate', 'Modules\AI\Http\Controllers\RecommendationsController@rate');
    Route::get('recommendations/user/{userId}/effectiveness', 'Modules\AI\Http\Controllers\RecommendationsController@getEffectiveness');
    Route::post('recommendations/user/{userId}/personalize', 'Modules\AI\Http\Controllers\RecommendationsController@personalize');

    // Natural Language Processing
    Route::post('nlp/intent', 'Modules\AI\Http\Controllers\NLPController@extractIntent');
    Route::post('nlp/sentiment', 'Modules\AI\Http\Controllers\NLPController@analyzeSentiment');
    Route::post('nlp/summarize', 'Modules\AI\Http\Controllers\NLPController@summarizeText');
    Route::post('nlp/keywords', 'Modules\AI\Http\Controllers\NLPController@extractKeywords');
    Route::post('nlp/classify', 'Modules\AI\Http\Controllers\NLPController@classifyCategory');
    Route::post('nlp/detect-language', 'Modules\AI\Http\Controllers\NLPController@detectLanguage');

    // Automated Insights
    Route::post('insights/generate', 'Modules\AI\Http\Controllers\InsightsController@generateInsights');
    Route::get('insights/{insightId}', 'Modules\AI\Http\Controllers\InsightsController@getDetails');
    Route::post('insights/{insightId1}/compare/{insightId2}', 'Modules\AI\Http\Controllers\InsightsController@compare');
    Route::get('insights/period/{startDate}/{endDate}', 'Modules\AI\Http\Controllers\InsightsController@getByPeriod');
    Route::get('insights/{insightId}/export/{format}', 'Modules\AI\Http\Controllers\InsightsController@export');

    // Contextual AI Assistant (AI Assisted First)
    Route::post('assist', 'Modules\AI\Http\Controllers\Api\AiAssistantController@assist');
    Route::get('assist/modules', 'Modules\AI\Http\Controllers\Api\AiAssistantController@modules');

    // Anomaly Detection
    Route::post('anomalies/detect', 'Modules\AI\Http\Controllers\Api\AiAnomalyController@detect');
    Route::get('anomalies', 'Modules\AI\Http\Controllers\Api\AiAnomalyController@index');
    Route::delete('anomalies/{id}', 'Modules\AI\Http\Controllers\Api\AiAnomalyController@dismiss');

    // Natural Language Search
    Route::post('search', 'Modules\AI\Http\Controllers\Api\AiSearchController@search');

    // Action Advisor
    Route::post('advise',    'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@advise');
    Route::get('usage/me',   'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@myUsage');
});

// Admin AI budget management (separate prefix, same sanctum guard)
Route::middleware(['auth:sanctum', 'session.security'])->prefix('v1/ai/admin')->group(function () {
    Route::get('usage',          'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminUsage');
    Route::get('limits',         'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminListLimits');
    Route::post('limits',        'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminSetLimit');
    Route::delete('limits/{id}', 'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminDeleteLimit');
});
