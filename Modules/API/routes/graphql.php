<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('graphql')->group(function () {
    // GraphQL Query Endpoint
    Route::post('query', 'Modules\API\Http\Controllers\GraphQL\GraphQLController@query');

    // GraphQL Mutations
    Route::post('mutation', 'Modules\API\Http\Controllers\GraphQL\GraphQLController@mutation');

    // GraphQL Subscriptions (WebSocket)
    Route::post('subscribe', 'Modules\API\Http\Controllers\GraphQL\SubscriptionController@subscribe');
    Route::post('unsubscribe/{subscriptionId}', 'Modules\API\Http\Controllers\GraphQL\SubscriptionController@unsubscribe');
    Route::get('subscriptions', 'Modules\API\Http\Controllers\GraphQL\SubscriptionController@getSubscriptions');
    Route::get('subscriptions/{subscriptionId}', 'Modules\API\Http\Controllers\GraphQL\SubscriptionController@getSubscription');

    // GraphQL Schema Management
    Route::post('schemas/generate', 'Modules\API\Http\Controllers\GraphQL\SchemaController@generate');
    Route::get('schemas', 'Modules\API\Http\Controllers\GraphQL\SchemaController@list');
    Route::get('schemas/{schemaId}', 'Modules\API\Http\Controllers\GraphQL\SchemaController@show');
    Route::post('schemas/{schemaId}/validate', 'Modules\API\Http\Controllers\GraphQL\SchemaController@validate');
    Route::post('schemas/merge', 'Modules\API\Http\Controllers\GraphQL\SchemaController@merge');

    // Query Optimization
    Route::post('optimize', 'Modules\API\Http\Controllers\GraphQL\OptimizerController@optimize');
    Route::post('validate-complexity/{queryId}', 'Modules\API\Http\Controllers\GraphQL\OptimizerController@validateComplexity');
    Route::get('optimize/{queryId}/report', 'Modules\API\Http\Controllers\GraphQL\OptimizerController@getReport');
    Route::post('dataloaders/create', 'Modules\API\Http\Controllers\GraphQL\OptimizerController@createDataLoader');
    Route::post('dataloaders/{loaderId}/queue', 'Modules\API\Http\Controllers\GraphQL\OptimizerController@queue');
    Route::post('dataloaders/{loaderId}/process', 'Modules\API\Http\Controllers\GraphQL\OptimizerController@processBatch');

    // API Versioning
    Route::get('versions', 'Modules\API\Http\Controllers\APIController@versions');
    Route::get('versions/{version}', 'Modules\API\Http\Controllers\APIController@versionInfo');
    Route::get('versions/{fromVersion}/breaking-changes/{toVersion}', 'Modules\API\Http\Controllers\APIController@breakingChanges');
    Route::get('versions/{fromVersion}/migration-guide/{toVersion}', 'Modules\API\Http\Controllers\APIController@migrationGuide');
    Route::get('versions/{version}/deprecated', 'Modules\API\Http\Controllers\APIController@deprecated');
    Route::get('versions/compatibility/matrix', 'Modules\API\Http\Controllers\APIController@compatibilityMatrix');
    Route::post('versions/check-compatibility', 'Modules\API\Http\Controllers\APIController@checkCompatibility');
});

// GraphQL Introspection (Public)
Route::get('graphql/__schema', 'Modules\API\Http\Controllers\GraphQL\IntrospectionController@schema');
Route::get('graphql/__type/{typeName}', 'Modules\API\Http\Controllers\GraphQL\IntrospectionController@type');
Route::get('graphql/__types', 'Modules\API\Http\Controllers\GraphQL\IntrospectionController@types');
