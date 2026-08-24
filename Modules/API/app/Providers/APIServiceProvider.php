<?php

namespace Modules\API\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\API\Http\Middleware\AuthenticateApiKey;
use Modules\API\Models\ApiKey;
use Modules\API\Policies\ApiKeyPolicy;
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

        // Chantier 32.5: registers the `api-key` middleware alias used by
        // routes/api.php's `ping` route — the previously-dead
        // api_keys/api_requests authentication+logging pipeline's one real,
        // self-contained consumer (see AuthenticateApiKey's own docblock).
        Route::aliasMiddleware('api-key', AuthenticateApiKey::class);

        Route::middleware('api')->prefix('api')->name('api.')->group(__DIR__ . '/../../routes/api.php');
    }

    /**
     * `ApiKeyPolicy` was correctly written (admin/super-admin only for
     * create/update/delete) but never registered with Laravel's Gate —
     * Modules-namespaced policies don't auto-discover the way `App\Policies`
     * ones do (same precedent as Core/BI/HR/Strategy) — and never called
     * from `ApiKeyController`. Any authenticated user of any role could
     * create/revoke API keys. Fixed at Chantier 8.5-light.
     *
     * Chantier 32.5: `WebhookPolicy`'s Gate::policy() registration was
     * removed here — the whole `ApiWebhook`/`WebhookController`/
     * `WebhookPolicy` subtree was deleted this chantier as a confirmed
     * dead/insecure duplicate of the real, live `App\Models\Webhook`
     * system (see the api_webhooks drop migration's own docblock for the
     * full rationale).
     */
    private function registerPolicies(): void
    {
        Gate::policy(ApiKey::class, ApiKeyPolicy::class);
    }
}
