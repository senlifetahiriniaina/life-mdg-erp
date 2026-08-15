<?php

namespace Modules\API\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\API\Services\GraphQLSchemaBuilderService;
use Modules\API\Services\GraphQLQueryOptimizerService;
use Modules\API\Services\GraphQLSubscriptionManagerService;
use Modules\API\Services\APIVersioningService;

class APIServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register API v2 Services
        $this->app->singleton(GraphQLSchemaBuilderService::class);
        $this->app->singleton(GraphQLQueryOptimizerService::class);
        $this->app->singleton(GraphQLSubscriptionManagerService::class);
        $this->app->singleton(APIVersioningService::class);

        // Service aliases
        $this->app->alias(GraphQLSchemaBuilderService::class, 'graphql_schema');
        $this->app->alias(GraphQLQueryOptimizerService::class, 'graphql_optimizer');
        $this->app->alias(GraphQLSubscriptionManagerService::class, 'graphql_subscriptions');
        $this->app->alias(APIVersioningService::class, 'api_versioning');
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }
}
