<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles and permissions must be seeded first (policies depend on them)
        $this->call(RolesAndPermissionsSeeder::class);

        // Chantier 12: default bootstrap admin — deliberately predictable
        // credentials (admin@life-mdg.com / admin) so a fresh install always
        // has a working first login, per explicit user request. This is a
        // real security tradeoff, not an oversight — see
        // docs/07-DEPLOIEMENT/CHECKLIST-GO-LIVE.md, which requires changing
        // this password before any real production go-live.
        $user = User::firstOrCreate(
            ['email' => 'admin@life-mdg.com'],
            [
                'name'     => 'Administrateur',
                'password' => Hash::make('admin'),
            ]
        );

        // Every seeded role, not just super-admin — super-admin already
        // bypasses every Gate check (see Gate::before across this session's
        // RBAC work), but several views/menus branch on a specific role name
        // (hasRole('accountant'), etc.), so the bootstrap admin needs every
        // role attached to actually see every department's screens.
        $user->syncRoles(Role::where('guard_name', 'web')->pluck('name')->all());

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

        // Real chart of accounts (76 SYSCOHADA-style accounts, adapted for
        // Madagascar) + default journals — previously written but never
        // actually reachable from this seed chain (only wired through the
        // broken TenantDefaultSeeder/ProvisionTenantJob path, see CLAUDE.md).
        $this->call(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);

        // Chantier 31: the amount-based, multi-tier purchase-order approval
        // routing (ApprovalRoutingService/PurchaseOrderService::submitForApproval())
        // was fully built and tested but never actually seeded in the real
        // app — confirmed empirically that a fresh install had zero
        // Achats ApprovalWorkflow rows, so no real approval chain was ever
        // created regardless of PO amount. See AchatsDatabaseSeeder's own
        // docblock.
        $this->call(\Modules\Achats\Database\Seeders\AchatsDatabaseSeeder::class);

        // Chantier 32.1: WorkflowDefinitionsSeeder (and the whole Core generic
        // FSM engine it fed — Modules\Core\Services\WorkflowService/
        // WorkflowController/WorkflowDefinition/WorkflowState, routed at
        // core/workflows/*) was deleted as confirmed-dead: zero real caller
        // anywhere ever created a WorkflowState for a real Invoice/Ticket/
        // Task/etc (WorkflowService::getOrCreateState() had zero callers
        // outside its own class), zero frontend page ever called
        // core/workflows/*, zero test covered any of it, and 4 of its 10
        // seeded definitions targeted modules entirely outside this app's
        // scope (manufacturing/pos/ecommerce). Every in-scope resource type
        // it named (Invoice, PurchaseOrder, Leave, Ticket, Task, Opportunity)
        // already has its own real status/state field and its own real
        // domain-specific transition logic — this generic engine was a
        // dead-parallel-subsystem duplicate, the same pattern already
        // deleted repeatedly this session (TerritoryManagementController,
        // wh_*/lgx_*, the Legacy Workflow Engine block, etc).

        // Chantier 12: minimal real-world defaults (company, customer,
        // supplier) distinct from DemoSeeder's illustrative French/EUR
        // sample dataset below — see DefaultDataSeeder's own docblock.
        $this->call(DefaultDataSeeder::class);

        // Chantier 17: textile/clothing product-template catalogue — depends
        // on the categories/units DefaultDataSeeder just created above.
        $this->call(\Modules\Inventory\Database\Seeders\ProductTemplateSeeder::class);

        // Chantier 32.12: CalendarDatabaseSeeder (3 default calendars for
        // the bootstrap admin, user_id=1) existed but was never actually
        // called from anywhere — confirmed via grep, the same "orphaned
        // module seeder" gap already fixed for Accounting at Chantier 12.
        // Idempotent (no-op once any calendar already exists).
        $this->call(\Modules\Calendar\Database\Seeders\CalendarDatabaseSeeder::class);

        $this->call(DemoSeeder::class);
    }
}
