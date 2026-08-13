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

        $modules = [
            'CRM', 'HR', 'Inventory', 'Accounting', 'Manufacturing',
            'POS', 'Ecommerce', 'BI', 'Email', 'Documents',
            'Helpdesk', 'Projects', 'WhatsApp',
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
