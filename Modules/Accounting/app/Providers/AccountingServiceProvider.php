<?php

namespace Modules\Accounting\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use Modules\Accounting\Models\FinancialSimulation;
use Modules\Accounting\Models\FinancialSimulationLine;
use Modules\Accounting\Models\GLAccount;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Observers\InvoiceObserver;
use Modules\Accounting\Policies\FinancialSimulationPolicy;
use Modules\Accounting\Policies\GLAccountPolicy;
use Modules\Accounting\Services\AccountingService;

class AccountingServiceProvider extends ServiceProvider {
    use PathNamespace;

    protected string $name = 'Accounting';
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(AccountingService::class, function ($app) {
            return new AccountingService;
        });
    }

    public function boot(): void
    {
        // Resolve Accounting factories from module namespace
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'Modules\\Accounting\\')) {
                $modelBase = class_basename($modelName);
                $factoryClass = "Modules\\Accounting\\Database\\Factories\\{$modelBase}Factory";
                if (class_exists($factoryClass)) {
                    return $factoryClass;
                }
            }
            return 'Database\\Factories\\' . str_replace('\\', '', $modelName) . 'Factory';
        });

        Invoice::observe(InvoiceObserver::class);
        Gate::policy(GLAccount::class, GLAccountPolicy::class);
        // Chantier 18: both the simulation and its lines (the `realize`
        // ability applies to a line, not the parent simulation) share the
        // same policy class.
        Gate::policy(FinancialSimulation::class, FinancialSimulationPolicy::class);
        Gate::policy(FinancialSimulationLine::class, FinancialSimulationPolicy::class);
        // Payment model does not exist yet; PaymentObserver available for future use
        // \Modules\Accounting\Models\Payment::observe(\Modules\Accounting\Observers\PaymentObserver::class);
$this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'accounting');
    }
}
