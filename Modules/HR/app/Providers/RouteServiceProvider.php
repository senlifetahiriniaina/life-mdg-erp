<?php

namespace Modules\HR\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\JobPosition;
use Modules\Payroll\Models\Payslip;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind('employee', function ($value) {
            return Employee::findOrFail($value);
        });

        Route::bind('department', function ($value) {
            return Department::findOrFail($value);
        });

        Route::bind('jobPosition', function ($value) {
            return JobPosition::findOrFail($value);
        });

        Route::bind('payslip', function ($value) {
            return Payslip::findOrFail($value);
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api/v1/hr')
                ->name('hr.')
                ->group(__DIR__.'/../../routes/api.php');

            Route::middleware('web')
                ->prefix('hr')
                ->name('hr.')
                ->group(__DIR__.'/../../routes/web.php');
        });
    }
}
