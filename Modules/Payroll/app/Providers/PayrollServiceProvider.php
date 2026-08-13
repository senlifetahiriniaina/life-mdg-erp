<?php

declare(strict_types=1);

namespace Modules\Payroll\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Payroll\Services\PayrollIntegrationService;

class PayrollServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(PayrollIntegrationService::class, function ($app) {
            return new PayrollIntegrationService();
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'payroll');
    }
}
