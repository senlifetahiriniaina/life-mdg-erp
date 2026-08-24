<?php

namespace Modules\Accounting\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use Modules\Accounting\Console\Commands\ImportChartOfAccountsCommand;
use Modules\Accounting\Console\Commands\ImportTreasuryHistoryCommand;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\FinanceReview;
use Modules\Accounting\Models\FinancialSimulation;
use Modules\Accounting\Models\FinancialSimulationLine;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\GLAccount;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Observers\InvoiceObserver;
use Modules\Accounting\Policies\CompanyPolicy;
use Modules\Accounting\Policies\FinanceReviewPolicy;
use Modules\Accounting\Policies\FinancialSimulationPolicy;
use Modules\Accounting\Policies\FiscalYearPolicy;
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
        $this->registerCommands();

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
        // Chantier 19 re-verification: ConsolidationController calls $this->authorize()
        // against Company (create/update/generateReport/recordTransaction/
        // eliminateIntercompany) but no policy for it existed anywhere — an unconditional
        // 403 on every mutating consolidation endpoint for every non-super-admin,
        // confirmed empirically. See CompanyPolicy's own docblock.
        Gate::policy(Company::class, CompanyPolicy::class);
        // Chantier 26 (volet D): FinanceReviewController calls authorize()
        // against FinanceReview — registered explicitly, matching the
        // Modules-namespaced-policies-don't-auto-discover precedent.
        Gate::policy(FinanceReview::class, FinanceReviewPolicy::class);
        // Chantier 32 (volet A1): FiscalYearController calls authorize()
        // against FiscalYear — registered explicitly, same precedent.
        Gate::policy(FiscalYear::class, FiscalYearPolicy::class);
        // Payment model does not exist yet; PaymentObserver available for future use
        // \Modules\Accounting\Models\Payment::observe(\Modules\Accounting\Observers\PaymentObserver::class);
$this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'accounting');
    }

    /**
     * Chantier 32 (volet A2) — commandes d'import formalisées au
     * déploiement (historique de trésorerie, plan comptable).
     */
    protected function registerCommands(): void
    {
        $this->commands([
            ImportTreasuryHistoryCommand::class,
            ImportChartOfAccountsCommand::class,
        ]);
    }
}
