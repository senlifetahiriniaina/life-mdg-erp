<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use App\Models\Admin\AuditLog;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Setup\Exceptions\ModuleDeactivationBlockedException;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Nwidart\Modules\Module;

/**
 * Was an explicit stub ("a full implementation was never written"; every
 * method returned ['implemented' => false, ...]). Now real, built on
 * nwidart/laravel-modules' own FileActivator (config('modules.activator'),
 * the package's tested read/write path for config/modules_statuses.json)
 * rather than hand-rolling JSON file I/O — reuses the library's own
 * enable()/disable() instead of duplicating what it already does safely.
 *
 * Adds what the library doesn't provide out of the box: validating a
 * client-supplied module name against the real module list before acting
 * on it (never trust a raw string into a filesystem/activation lookup),
 * blocking a deactivation that would break another active module's
 * declared `requires`, and an audit trail.
 */
class ModuleManagerService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(string $tenantId): array
    {
        return $this->all()
            ->map(fn (Module $module) => $this->present($module))
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(string $moduleName, string $tenantId, ?int $userId = null): array
    {
        $module = $this->findOrFail($moduleName);
        $module->enable();

        AuditLog::record('module_activate', $userId, 'Module', null, ['module' => $module->getName()]);

        return $this->present($module);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ModuleDeactivationBlockedException when an active module still requires this one
     */
    public function deactivate(string $moduleName, string $tenantId, ?int $userId = null): array
    {
        $module = $this->findOrFail($moduleName);

        $dependents = $this->activeDependents($module->getName());

        if ($dependents->isNotEmpty()) {
            throw new ModuleDeactivationBlockedException($module->getName(), $dependents->all());
        }

        $module->disable();

        AuditLog::record('module_deactivate', $userId, 'Module', null, ['module' => $module->getName()]);

        return $this->present($module);
    }

    /**
     * @param  array<int, string>  $moduleNames
     * @return array<int, array<string, mixed>>
     */
    public function bulkActivate(array $moduleNames, string $tenantId, ?int $userId = null): array
    {
        return array_map(
            fn (string $name) => $this->activate($name, $tenantId, $userId),
            $moduleNames,
        );
    }

    private function findOrFail(string $moduleName): Module
    {
        $module = ModuleFacade::find($moduleName);

        if (! $module) {
            throw new InvalidArgumentException("Unknown module: {$moduleName}");
        }

        return $module;
    }

    /** @return Collection<int, Module> */
    private function all(): Collection
    {
        return collect(ModuleFacade::all())->values();
    }

    /**
     * Every OTHER currently-enabled module whose module.json `requires`
     * array names this module — refuses a deactivation that would strand
     * a dependent rather than silently breaking it.
     *
     * @return Collection<int, string>
     */
    private function activeDependents(string $moduleName): Collection
    {
        return collect(ModuleFacade::allEnabled())
            ->reject(fn (Module $m) => strcasecmp($m->getName(), $moduleName) === 0)
            ->filter(fn (Module $m) => in_array($moduleName, (array) $m->get('requires', []), true))
            ->map(fn (Module $m) => $m->getName())
            ->values();
    }

    /**
     * Reads 'priority'/'description' via $module->get() (raw module.json
     * access) rather than getPriority()/getDescription() — those library
     * getters have a non-nullable string return type and fatal on any
     * module.json missing the key, which several in this repo do.
     *
     * @return array<string, mixed>
     */
    private function present(Module $module): array
    {
        return [
            'name' => $module->getName(),
            'description' => (string) $module->get('description', ''),
            'priority' => $module->get('priority', 0),
            'is_active' => $module->isEnabled(),
            'requires' => (array) $module->get('requires', []),
        ];
    }
}
