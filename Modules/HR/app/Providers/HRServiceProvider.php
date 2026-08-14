<?php

namespace Modules\HR\Providers;

use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Observers\EmployeeObserver;
use Modules\HR\Observers\LeaveRequestObserver;
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

        // Register artisan command for document expiry checks
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\HR\Console\Commands\CheckDocumentExpiry::class,
            ]);
        }
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
