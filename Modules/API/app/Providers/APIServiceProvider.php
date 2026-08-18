<?php

namespace Modules\API\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\API\Models\ApiKey;
use Modules\API\Models\ApiWebhook;
use Modules\API\Policies\ApiKeyPolicy;
use Modules\API\Policies\WebhookPolicy;
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
        $this->registerPolicies();

        Route::middleware('api')->prefix('api')->name('api.')->group(__DIR__ . '/../../routes/api.php');
    }

    /**
     * `ApiKeyPolicy`/`WebhookPolicy` were correctly written (admin/super-admin/
     * api-manager only for create/update/delete) but never registered with
     * Laravel's Gate — Modules-namespaced policies don't auto-discover the way
     * `App\Policies` ones do (same precedent as Core/BI/HR/Strategy) — and never
     * called from `ApiKeyController`/`WebhookController`. Any authenticated user
     * of any role could create/revoke API keys and webhooks. The real webhook
     * model class is `Modules\API\Models\ApiWebhook` (table `api_webhooks`) —
     * there is no `Webhook` model in this module.
     */
    private function registerPolicies(): void
    {
        Gate::policy(ApiKey::class, ApiKeyPolicy::class);
        Gate::policy(ApiWebhook::class, WebhookPolicy::class);
    }
}
