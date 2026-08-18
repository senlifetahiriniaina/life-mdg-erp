<?php

namespace Modules\HR\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Modules\HR\Models\AttendanceException;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\BiometricDevice;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\ShiftSchedule;
use Modules\HR\Models\TimeOffRequest;
use Modules\HR\Observers\EmployeeObserver;
use Modules\HR\Observers\LeaveRequestObserver;
use Modules\HR\Policies\AttendancePolicy;
use Modules\HR\Services\DocumentExpiryService;
use Modules\HR\Services\HRService;

class HRServiceProvider extends ServiceProvider {
    use PathNamespace;

    protected string $name = 'HR';

    protected string $nameLower = 'hr';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerConfig();

        $this->app->singleton(HRService::class, function ($app) {
            return new HRService;
        });

        $this->app->singleton(DocumentExpiryService::class);
    }

    public function boot(): void
    {
        Employee::observe(EmployeeObserver::class);
        LeaveRequest::observe(LeaveRequestObserver::class);
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'hr');
        $this->registerPolicies();

        // Register artisan command for document expiry checks
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\HR\Console\Commands\CheckDocumentExpiry::class,
            ]);
        }
    }

    /**
     * Chantier 8.3: AttendancePolicy is fully written (13 abilities backing
     * AttendanceBiometricController) but, like every other Modules-namespaced
     * policy in this app, doesn't auto-discover — its class name doesn't match
     * any single model's name (it backs 5: BiometricDevice/AttendanceRecord/
     * AttendanceException/TimeOffRequest/ShiftSchedule), so it must be
     * registered explicitly for each one.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(BiometricDevice::class, AttendancePolicy::class);
        Gate::policy(AttendanceRecord::class, AttendancePolicy::class);
        Gate::policy(AttendanceException::class, AttendancePolicy::class);
        Gate::policy(TimeOffRequest::class, AttendancePolicy::class);
        Gate::policy(ShiftSchedule::class, AttendancePolicy::class);
    }

    protected function registerConfig(): void
    {
        $relativeConfigPath = config('modules.paths.generator.config.path');
        $configPath = module_path($this->name, $relativeConfigPath);

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $key = ($relativePath === 'config.php') ? $this->nameLower : $this->nameLower.'.'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);

                    $this->mergeConfigFrom($file->getPathname(), $key);
                }
            }
        }
    }
}
