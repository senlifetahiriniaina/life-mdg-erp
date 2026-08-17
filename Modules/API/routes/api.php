<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/api')->group(function () {
    Route::get('keys', 'Modules\API\Http\Controllers\Api\ApiKeyController@index');
    Route::post('keys', 'Modules\API\Http\Controllers\Api\ApiKeyController@store');
    Route::get('keys/{id}', 'Modules\API\Http\Controllers\Api\ApiKeyController@show');
    Route::delete('keys/{id}/revoke', 'Modules\API\Http\Controllers\Api\ApiKeyController@revoke');
    Route::get('keys/{id}/logs', 'Modules\API\Http\Controllers\Api\ApiKeyController@logs');

    Route::get('webhooks', 'Modules\API\Http\Controllers\Api\WebhookController@index');
    Route::post('webhooks', 'Modules\API\Http\Controllers\Api\WebhookController@store');
    Route::put('webhooks/{id}', 'Modules\API\Http\Controllers\Api\WebhookController@update');
    Route::delete('webhooks/{id}', 'Modules\API\Http\Controllers\Api\WebhookController@destroy');
    Route::post('webhooks/{id}/test', 'Modules\API\Http\Controllers\Api\WebhookController@test');

    Route::get('logs', 'Modules\API\Http\Controllers\Api\RequestLogController@index');
    Route::get('logs/stats', 'Modules\API\Http\Controllers\Api\RequestLogController@stats');
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/api')->group(function () {
    Route::post('ai/assist', [\Modules\API\Http\Controllers\Api\APIAiAssistController::class, 'assist'])
        ->name('api.ai.assist');
});
