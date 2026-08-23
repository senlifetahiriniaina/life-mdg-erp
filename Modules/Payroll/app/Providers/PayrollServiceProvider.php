<?php

declare(strict_types=1);

namespace Modules\Payroll\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Services\AccountRoleService;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Observers\PayslipObserver;
use Modules\Payroll\Policies\PayrollPolicy;
use Modules\Payroll\Services\PayrollIntegrationService;

class PayrollServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(PayrollIntegrationService::class, function ($app) {
            return new PayrollIntegrationService($app->make(AccountRoleService::class));
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'payroll');
        $this->registerPolicies();

        // Chantier 32.18: Payroll had never wired into Chantier 20's
        // ParticipantNotificationService — an employee's payslip being
        // approved/paid is a real process nothing notified them about.
        Payslip::observe(PayslipObserver::class);
    }

    /**
     * Modules-namespaced policies don't auto-discover the way App\Policies
     * ones do (Laravel's convention resolver only looks under the app's
     * own namespace) — same pattern as Core/BI/HR's registerPolicies().
     */
    private function registerPolicies(): void
    {
        Gate::policy(Payslip::class, PayrollPolicy::class);
    }
}
