<?php

namespace Modules\Timesheets\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimesheetPeriod;
use Modules\Timesheets\Policies\TimesheetEntryPolicy;
use Modules\Timesheets\Policies\TimesheetPeriodPolicy;
use Nwidart\Modules\Traits\PathNamespace;

class TimesheetsServiceProvider extends ServiceProvider {
    use PathNamespace;

    protected string $name = 'Timesheets';
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->registerPolicies();
    }

    /**
     * Chantier 10: TimesheetEntryPolicy was fully written and already called
     * via $this->authorize() throughout TimesheetEntryController
     * (viewAny/update/delete/approve on every real request, including
     * index()'s viewAny call), but was never registered with the Gate —
     * Modules-namespaced policies don't auto-discover the way App\Policies
     * ones do (same pattern as Core/BI/HR/Payroll's registerPolicies()).
     * With no policy bound to TimesheetEntry, every authorize() call denied
     * unconditionally — every timesheet entry list/update/delete/approve
     * request 403'd for every user, including admins. Active breakage on a
     * core, frequently-used feature, not a hypothetical gap.
     */
    private function registerPolicies(): void
    {
        Gate::policy(TimesheetEntry::class, TimesheetEntryPolicy::class);

        // Chantier 19 (Lot 2): TimesheetPeriod ("sheets") had no Policy at
        // all — see TimesheetPeriodPolicy's own docblock for the active
        // RBAC gap this closes.
        Gate::policy(TimesheetPeriod::class, TimesheetPeriodPolicy::class);
    }
}
