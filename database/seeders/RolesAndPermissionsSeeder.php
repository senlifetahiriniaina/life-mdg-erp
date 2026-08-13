<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the ERP role hierarchy and granular permissions.
 *
 * Roles (highest → lowest privilege):
 *   super-admin          — bypasses all Gate checks (Gate::before)
 *   admin                — full access to all modules
 *   manager              — create/update/delete within assigned modules
 *   employee             — read + create own records
 *   accountant           — full access to Accounting module only
 *   hr-manager           — full access to HR module only
 *   sales-rep            — full access to CRM module only
 *   — admin roles —
 *   system-admin         — infrastructure & server management
 *   security-admin       — audit, 2FA policy, sessions
 *   billing-admin        — billing and subscriptions
 *   support-admin        — helpdesk management
 *   content-admin        — documents, KB, email templates
 *   tenant-admin         — module toggle and user/role management
 *   — operational roles —
 *   cashier              — POS operations only
 *   community-manager    — social/marketing: WhatsApp, Email, CRM contacts
 *   production-manager   — full Manufacturing module access
 *   logistics-manager    — full Inventory + POS order view
 *   service-partner      — external service provider: Helpdesk, Documents, Projects
 *   brand-owner          — marketplace partner: Ecommerce store & products
 *   purchasing-manager   — procurement: Inventory + supplier + PO management
 *   warehouse-operator   — physical warehouse: products, stock movements
 *   sales-manager        — CRM full + BI dashboards and reports
 *   project-manager      — full Projects module + HR employee view
 *   finance-manager      — full Accounting + BI
 *   customer-service     — full Helpdesk + CRM contact/account view
 *   inventory-analyst    — read-only Inventory + BI analytics
 *   marketplace-admin    — full Ecommerce + Inventory products + CRM accounts
 *
 * Permission format: {module}.{resource}.{action}
 * Actions: view-any | view | create | update | delete
 */
class RolesAndPermissionsSeeder extends Seeder
{
    private const ADMIN_PERMISSIONS = [
        'admin.servers.view',
        'admin.servers.manage',
        'admin.backups.view',
        'admin.backups.create',
        'admin.backups.restore',
        'admin.users.view',
        'admin.users.create',
        'admin.users.update',
        'admin.users.delete',
        'admin.roles.view',
        'admin.roles.assign',
        'admin.modules.view',
        'admin.modules.toggle',
        'admin.audit.view',
        'admin.security.manage',
        'admin.billing.view',
        'admin.billing.manage',
    ];

    private const MODULES = [
        'crm'              => ['contact', 'lead', 'opportunity', 'account', 'activity', 'pipeline'],
        'hr'               => ['employee', 'department', 'job-position', 'leave', 'leave-type'],
        'inventory'        => ['product', 'category', 'warehouse', 'unit', 'stock-movement', 'purchase-order', 'supplier'],
        'accounting'       => ['invoice', 'journal', 'chart-of-account'],
        'manufacturing'    => ['production-order', 'bom', 'workcenter'],
        'pos'              => ['pos-order', 'pos-session', 'cash-movement', 'shift', 'loyalty'],
        'ecommerce'        => ['order', 'store', 'product', 'marketplace', 'vendor'],
        'helpdesk'         => ['ticket', 'team'],
        'projects'         => ['project', 'task'],
        'documents'        => ['document', 'folder'],
        'bi'               => ['dashboard', 'kpi', 'report'],
        'email'            => ['campaign', 'template', 'subscriber'],
        'whatsapp'         => ['conversation', 'broadcast', 'template'],
        'auditlog'         => ['logs'],
        // Modules promoted to COMPLET (Phase 52)
        'assets'           => ['asset', 'maintenance', 'depreciation'],
        'contracts'        => ['contract', 'template', 'renewal'],
        'sales'            => ['order', 'line', 'quotation'],
        'sms'              => ['message', 'template', 'campaign'],
        'setup'            => ['import', 'mapping', 'wizard'],
        'reporting'        => ['report', 'template', 'schedule'],
        'integration'      => ['connector', 'webhook', 'sync-log'],
        'smart_tables'     => ['table', 'column', 'row', 'base'],
        'notes'            => ['page', 'attachment'],
        'customerservice'  => ['ticket', 'escalation', 'category'],
        'settings'         => ['setting', 'group'],
        'payroll'          => ['payslip', 'run', 'tax-config'],
    ];

    private const ACTIONS = ['view-any', 'view', 'create', 'update', 'delete'];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create admin-specific permissions
        $adminPermissions = [];
        foreach (self::ADMIN_PERMISSIONS as $permName) {
            $adminPermissions[] = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // Create all module permissions
        $allPermissions = [];
        foreach (self::MODULES as $module => $resources) {
            foreach ($resources as $resource) {
                foreach (self::ACTIONS as $action) {
                    $name = "{$module}.{$resource}.{$action}";
                    $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                }
            }
        }

        // ── Roles ──────────────────────────────────────────────────────────────

        // super-admin: Gate::before bypass — no permission assignment needed
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // admin: every permission
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($allPermissions);

        // manager: all actions except delete on sensitive resources
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerPerms = array_filter($allPermissions, function (Permission $p) {
            // No delete on invoices/employees/documents from managers
            if (str_ends_with($p->name, '.delete') &&
                (str_starts_with($p->name, 'accounting.') || str_starts_with($p->name, 'hr.') || str_starts_with($p->name, 'documents.'))) {
                return false;
            }
            return true;
        });
        $manager->syncPermissions(array_values($managerPerms));

        // employee: view-any + view + create + update own (no delete)
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $employeePerms = array_filter($allPermissions, fn(Permission $p) => ! str_ends_with($p->name, '.delete'));
        $employee->syncPermissions(array_values($employeePerms));

        // accountant: full access to accounting only
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions(
            array_filter($allPermissions, fn(Permission $p) => str_starts_with($p->name, 'accounting.'))
        );

        // hr-manager: full access to HR only
        $hrManager = Role::firstOrCreate(['name' => 'hr-manager', 'guard_name' => 'web']);
        $hrManager->syncPermissions(
            array_filter($allPermissions, fn(Permission $p) => str_starts_with($p->name, 'hr.'))
        );

        // sales-rep: full access to CRM only
        $salesRep = Role::firstOrCreate(['name' => 'sales-rep', 'guard_name' => 'web']);
        $salesRep->syncPermissions(
            array_filter($allPermissions, fn(Permission $p) => str_starts_with($p->name, 'crm.'))
        );

        // ── New admin roles ────────────────────────────────────────────────────

        // system-admin: servers, deployments, backups, system logs
        $systemAdmin = Role::firstOrCreate(['name' => 'system-admin', 'guard_name' => 'web']);
        $systemAdmin->syncPermissions(array_filter(
            $adminPermissions,
            fn(Permission $p) => in_array($p->name, [
                'admin.servers.view', 'admin.servers.manage',
                'admin.backups.view', 'admin.backups.create', 'admin.backups.restore',
                'admin.audit.view',
            ])
        ));

        // security-admin: audit logs, 2FA policy, sessions, API keys
        $securityAdmin = Role::firstOrCreate(['name' => 'security-admin', 'guard_name' => 'web']);
        $securityAdmin->syncPermissions(array_filter(
            $allPermissions,
            fn(Permission $p) => in_array($p->name, [
                'admin.audit.view', 'admin.security.manage', 'admin.users.view',
                'auditlog.logs.view-any', 'auditlog.logs.view',
            ])
        ));

        // billing-admin: plans, subscriptions, billing
        $billingAdmin = Role::firstOrCreate(['name' => 'billing-admin', 'guard_name' => 'web']);
        $billingAdmin->syncPermissions(array_filter(
            $adminPermissions,
            fn(Permission $p) => in_array($p->name, [
                'admin.billing.view', 'admin.billing.manage',
            ])
        ));

        // support-admin: all helpdesk tickets, escalations
        $supportAdmin = Role::firstOrCreate(['name' => 'support-admin', 'guard_name' => 'web']);
        $supportAdmin->syncPermissions(array_filter(
            $allPermissions,
            fn(Permission $p) => str_starts_with($p->name, 'helpdesk.')
        ));

        // content-admin: documents, KB, email templates
        $contentAdmin = Role::firstOrCreate(['name' => 'content-admin', 'guard_name' => 'web']);
        $contentAdmin->syncPermissions(array_filter(
            $allPermissions,
            fn(Permission $p) => str_starts_with($p->name, 'documents.') || str_starts_with($p->name, 'email.')
        ));

        // tenant-admin: manages enabled modules for their tenant
        $tenantAdmin = Role::firstOrCreate(['name' => 'tenant-admin', 'guard_name' => 'web']);
        $tenantAdmin->syncPermissions(array_filter(
            $adminPermissions,
            fn(Permission $p) => in_array($p->name, [
                'admin.modules.view', 'admin.modules.toggle',
                'admin.users.view', 'admin.users.create', 'admin.users.update',
                'admin.roles.view', 'admin.roles.assign',
            ])
        ));

        // admin: gets all module permissions + all admin permissions
        $admin->syncPermissions(array_merge($allPermissions, $adminPermissions));

        // ── Operational roles (14 new) ─────────────────────────────────────

        // cashier: POS operations only (orders, sessions, cash, shifts, loyalty)
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'pos.') ||
            in_array($p->name, ['inventory.product.view-any', 'inventory.product.view'])
        ));

        // community-manager: WhatsApp + Email + CRM contacts/leads only
        $communityManager = Role::firstOrCreate(['name' => 'community-manager', 'guard_name' => 'web']);
        $communityManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'whatsapp.') ||
            str_starts_with($p->name, 'email.') ||
            in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view', 'crm.contact.create', 'crm.contact.update',
                'crm.lead.view-any', 'crm.lead.view', 'crm.lead.create', 'crm.lead.update', 'crm.lead.delete',
                'crm.activity.view-any', 'crm.activity.view', 'crm.activity.create',
            ])
        ));

        // production-manager: full Manufacturing module
        $productionManager = Role::firstOrCreate(['name' => 'production-manager', 'guard_name' => 'web']);
        $productionManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'manufacturing.') ||
            in_array($p->name, ['inventory.product.view-any', 'inventory.product.view', 'inventory.warehouse.view-any', 'inventory.warehouse.view'])
        ));

        // logistics-manager: full Inventory + POS order view
        $logisticsManager = Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $logisticsManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'inventory.') ||
            in_array($p->name, ['pos.pos-order.view-any', 'pos.pos-order.view', 'ecommerce.order.view-any', 'ecommerce.order.view'])
        ));

        // service-partner: external service provider (Helpdesk tickets, Documents view, Projects tasks)
        $servicePartner = Role::firstOrCreate(['name' => 'service-partner', 'guard_name' => 'web']);
        $servicePartner->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'helpdesk.ticket.') ||
            in_array($p->name, [
                'documents.document.view-any', 'documents.document.view',
                'projects.task.view-any', 'projects.task.view', 'projects.task.create', 'projects.task.update',
                'projects.project.view-any', 'projects.project.view',
            ])
        ));

        // brand-owner: marketplace partner (Ecommerce store & products, view orders)
        $brandOwner = Role::firstOrCreate(['name' => 'brand-owner', 'guard_name' => 'web']);
        $brandOwner->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            in_array($p->name, [
                'ecommerce.store.view-any', 'ecommerce.store.view', 'ecommerce.store.create', 'ecommerce.store.update',
                'ecommerce.product.view-any', 'ecommerce.product.view', 'ecommerce.product.create', 'ecommerce.product.update', 'ecommerce.product.delete',
                'ecommerce.order.view-any', 'ecommerce.order.view',
                'ecommerce.marketplace.view-any', 'ecommerce.marketplace.view', 'ecommerce.marketplace.manage',
                'ecommerce.vendor.view-any', 'ecommerce.vendor.view', 'ecommerce.vendor.create', 'ecommerce.vendor.update',
            ])
        ));

        // purchasing-manager: procurement (Inventory + suppliers + POs + Accounting invoices)
        $purchasingManager = Role::firstOrCreate(['name' => 'purchasing-manager', 'guard_name' => 'web']);
        $purchasingManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'inventory.') ||
            in_array($p->name, [
                'accounting.invoice.view-any', 'accounting.invoice.view',
                'accounting.invoice.create', 'accounting.invoice.update',
            ])
        ));

        // warehouse-operator: physical warehouse (products, stock movements, warehouse view)
        $warehouseOperator = Role::firstOrCreate(['name' => 'warehouse-operator', 'guard_name' => 'web']);
        $warehouseOperator->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            in_array($p->name, [
                'inventory.product.view-any', 'inventory.product.view', 'inventory.product.update',
                'inventory.stock-movement.view-any', 'inventory.stock-movement.view',
                'inventory.stock-movement.create', 'inventory.stock-movement.update',
                'inventory.warehouse.view-any', 'inventory.warehouse.view',
                'inventory.category.view-any', 'inventory.category.view',
                'inventory.unit.view-any', 'inventory.unit.view',
            ])
        ));

        // sales-manager: CRM full access + BI reports/dashboards
        $salesManager = Role::firstOrCreate(['name' => 'sales-manager', 'guard_name' => 'web']);
        $salesManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'crm.') ||
            in_array($p->name, [
                'bi.dashboard.view-any', 'bi.dashboard.view',
                'bi.report.view-any', 'bi.report.view',
                'bi.kpi.view-any', 'bi.kpi.view',
                'accounting.invoice.view-any', 'accounting.invoice.view',
            ])
        ));

        // project-manager: full Projects module + HR employee view
        $projectManager = Role::firstOrCreate(['name' => 'project-manager', 'guard_name' => 'web']);
        $projectManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'projects.') ||
            in_array($p->name, [
                'hr.employee.view-any', 'hr.employee.view',
                'hr.department.view-any', 'hr.department.view',
                'documents.document.view-any', 'documents.document.view',
                'documents.document.create', 'documents.document.update',
                'documents.folder.view-any', 'documents.folder.view',
            ])
        ));

        // finance-manager: full Accounting + full BI
        $financeManager = Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);
        $financeManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'accounting.') ||
            str_starts_with($p->name, 'bi.')
        ));

        // customer-service: full Helpdesk + CRM contact/account view
        $customerService = Role::firstOrCreate(['name' => 'customer-service', 'guard_name' => 'web']);
        $customerService->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'helpdesk.') ||
            in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view',
                'crm.account.view-any', 'crm.account.view',
                'crm.activity.view-any', 'crm.activity.view', 'crm.activity.create',
            ])
        ));

        // inventory-analyst: read-only Inventory + full BI
        $inventoryAnalyst = Role::firstOrCreate(['name' => 'inventory-analyst', 'guard_name' => 'web']);
        $inventoryAnalyst->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            (str_starts_with($p->name, 'inventory.') && in_array(explode('.', $p->name)[2] ?? '', ['view-any', 'view'])) ||
            str_starts_with($p->name, 'bi.')
        ));

        // marketplace-admin: full Ecommerce + Inventory products + CRM accounts
        $marketplaceAdmin = Role::firstOrCreate(['name' => 'marketplace-admin', 'guard_name' => 'web']);
        $marketplaceAdmin->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'ecommerce.') ||
            str_starts_with($p->name, 'inventory.product.') ||
            str_starts_with($p->name, 'inventory.category.') ||
            in_array($p->name, [
                'crm.account.view-any', 'crm.account.view', 'crm.account.create', 'crm.account.update',
                'bi.report.view-any', 'bi.report.view', 'bi.dashboard.view-any', 'bi.dashboard.view',
            ])
        ));

        $totalRoles = 27; // 13 original + 14 new operational roles
        $this->command->info(sprintf(
            'Seeded %d permissions across %d roles.',
            count($allPermissions) + count($adminPermissions),
            $totalRoles
        ));
    }
}
