<?php

namespace Modules\AuditLog\Providers;

use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AuditLogServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'AuditLog';

    protected string $nameLower = 'auditlog';

    public function boot(): void
    {
        $this->registerConfig();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
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

    public function provides(): array
    {
        return [];
    }
}
