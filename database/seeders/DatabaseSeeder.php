<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles and permissions must be seeded first (policies depend on them)
        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::firstOrCreate(
            ['email' => 'admin@widehalo.com'],
            [
                'name'     => 'Admin User',
                'password' => Hash::make('Admin#Wh2025!'),
            ]
        );

        $user->syncRoles(['super-admin']);

        // Real Life MDG 27-module scope (see CLAUDE.md's scope table) — was a stale
        // 13-module list copied from WideHalo-ERP's old 47-module scope (mirrored the
        // same bug fixed in tests/Pest.php's actingAsUser() helper).
        $modules = [
            'Core', 'AI', 'Security', 'AuditLog', 'API', 'Integration', 'Validation',
            'Shared', 'Settings', 'Setup', 'Workflow', 'Calendar',
            'Accounting', 'CRM', 'Sales',
            'Inventory', 'Logistics', 'Achats',
            'BI', 'Analytics', 'Reporting', 'Strategy',
            'HR', 'Payroll', 'Timesheets', 'Projects',
            'Helpdesk',
        ];

        foreach ($modules as $module) {
            DB::table('tenant_modules')->updateOrInsert(
                ['tenant_id' => (string) $user->id, 'module' => $module, 'department' => null],
                ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $this->call(WorkflowDefinitionsSeeder::class);

        $this->call(DemoSeeder::class);
    }
}
