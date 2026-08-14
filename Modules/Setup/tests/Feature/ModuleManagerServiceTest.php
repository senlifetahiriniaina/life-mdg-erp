<?php

namespace Modules\Setup\Tests\Feature;

use InvalidArgumentException;
use Modules\Setup\Exceptions\ModuleDeactivationBlockedException;
use Modules\Setup\Services\ModuleManagerService;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Tests\TestCase;

/**
 * ModuleManagerService was an explicit stub before this change (every
 * method returned ['implemented' => false, ...]). These tests exercise it
 * against the REAL config/modules_statuses.json via nwidart's own
 * FileActivator — setUp()/tearDown() snapshot and restore that file's raw
 * bytes around every test so a failed assertion can never leave the repo's
 * actual module-activation state corrupted.
 */
class ModuleManagerServiceTest extends TestCase
{
    private string $statusesFile;

    private string $originalContents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statusesFile = config('modules.activators.file.statuses-file');
        $this->originalContents = file_get_contents($this->statusesFile);
    }

    protected function tearDown(): void
    {
        file_put_contents($this->statusesFile, $this->originalContents);
        parent::tearDown();
    }

    public function test_get_all_lists_every_module_with_its_active_state()
    {
        $modules = app(ModuleManagerService::class)->getAll('default');

        $this->assertNotEmpty($modules);
        $names = array_column($modules, 'name');
        $this->assertContains('HR', $names);

        $hr = collect($modules)->firstWhere('name', 'HR');
        $this->assertTrue($hr['is_active']);
        $this->assertArrayHasKey('requires', $hr);
    }

    public function test_deactivate_then_activate_round_trips_the_statuses_file()
    {
        // Timesheets: confirmed via grep across every Modules/*/module.json
        // that only Payroll declares a non-empty "requires" (["HR",
        // "Accounting"]) — Timesheets is a safe leaf with no dependents.
        $service = app(ModuleManagerService::class);

        $result = $service->deactivate('Timesheets', 'default');
        $this->assertFalse($result['is_active']);
        $this->assertFalse(ModuleFacade::find('Timesheets')->isEnabled());

        $onDisk = json_decode(file_get_contents($this->statusesFile), true);
        $this->assertFalse($onDisk['Timesheets']);

        $result = $service->activate('Timesheets', 'default');
        $this->assertTrue($result['is_active']);
        $this->assertTrue(ModuleFacade::find('Timesheets')->isEnabled());
    }

    public function test_deactivating_a_module_another_active_module_requires_is_blocked()
    {
        $service = app(ModuleManagerService::class);

        $this->expectException(ModuleDeactivationBlockedException::class);

        try {
            $service->deactivate('HR', 'default');
        } catch (ModuleDeactivationBlockedException $e) {
            $this->assertContains('Payroll', $e->getDependents());
            $this->assertTrue(ModuleFacade::find('HR')->isEnabled(), 'HR must remain enabled after a blocked deactivation');

            throw $e;
        }
    }

    public function test_activating_an_unknown_module_throws()
    {
        $this->expectException(InvalidArgumentException::class);

        app(ModuleManagerService::class)->activate('DefinitelyNotARealModule', 'default');
    }

    public function test_bulk_activate_processes_every_module()
    {
        $results = app(ModuleManagerService::class)->bulkActivate(['Timesheets', 'HR'], 'default');

        $this->assertCount(2, $results);
        $this->assertTrue(collect($results)->every(fn ($r) => $r['is_active']));
    }
}
