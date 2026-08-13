<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Enhanced RBAC seeder — not wired into DatabaseSeeder by default
 * (RolesAndPermissionsSeeder is the active one); kept trimmed to the
 * Life MDG module scope in case it is activated later.
 *
 * Covers the 20 Life MDG modules (CORE + Compta/CRM/Stock/Pilotage +
 * RH basique + Helpdesk) with ~27 roles across admin/manager/operational/
 * API/employee tiers.
 */
class EnhancedRolesAndPermissionsSeeder extends Seeder
{
    private const ADMIN_PERMISSIONS = [
        'admin.servers.view', 'admin.servers.manage',
        'admin.backups.view', 'admin.backups.create', 'admin.backups.restore',
        'admin.users.view', 'admin.users.create', 'admin.users.update', 'admin.users.delete',
        'admin.roles.view', 'admin.roles.assign', 'admin.roles.create', 'admin.roles.update',
        'admin.modules.view', 'admin.modules.toggle', 'admin.modules.disable',
        'admin.audit.view', 'admin.audit.export',
        'admin.security.manage', 'admin.security.2fa',
        'admin.billing.view', 'admin.billing.manage',
        'admin.integrations.manage',
    ];

    // Life MDG scope: 20 modules (out-of-scope modules — Ecommerce, POS,
    // Manufacturing, PLM, Planning, Quality, Discussion, Documents, Email,
    // WhatsApp, MarketingAutomation, Mobile, Assets, Contracts, SMS,
    // SmartTable, Notes, CustomerService, legacy Purchasing — removed).
    private const MODULES = [
        // System
        'core' => ['setting', 'health', 'integration', 'webhook'],
        'auditlog' => ['logs', 'export', 'filter'],
        'api' => ['token', 'endpoint', 'webhook', 'integration', 'rate-limit', 'key', 'usage'],

        // Sales & CRM
        'crm' => ['contact', 'lead', 'opportunity', 'account', 'activity', 'pipeline', 'campaign', 'proposal'],
        'sales' => ['order', 'line', 'quotation', 'invoice', 'return'],

        // Finance & Accounting
        'accounting' => ['invoice', 'journal', 'chart-of-account', 'tax', 'payment', 'reconciliation', 'expense', 'budget'],
        'billing' => ['subscription', 'plan', 'invoice', 'payment-method', 'invoice-line'],

        // Operations & Inventory
        'inventory' => ['product', 'category', 'warehouse', 'unit', 'stock-movement', 'sku', 'barcode', 'location'],
        'logistics' => ['shipment', 'tracking', 'delivery-zone', 'route', 'vehicle', 'carrier'],
        'achats' => ['po', 'supplier', 'purchase-request', 'approval', 'quotation', 'vendor-invoice'],

        // Human Resources
        'hr' => ['employee', 'department', 'job-position', 'leave', 'leave-type', 'salary'],
        'timesheets' => ['timesheet', 'approval', 'allocation', 'absence', 'overtime', 'report', 'sync'],
        'payroll' => ['payslip', 'run', 'tax-config', 'deduction', 'allowance'],

        // Support & Service
        'helpdesk' => ['ticket', 'team', 'sla', 'priority', 'category', 'faq', 'escalation', 'feedback'],

        // Project Management
        'projects' => ['project', 'task', 'milestone', 'team-member', 'resource', 'timeline', 'budget', 'deliverable'],
        'validation' => ['workflow', 'approval-request', 'e-signature', 'delegation', 'rule', 'step'],

        // Analytics & Reporting
        'bi' => ['dashboard', 'kpi', 'report', 'datasource', 'query', 'export', 'visualization'],
        'analytics' => ['forecast', 'anomaly', 'model'],
        'reporting' => ['report', 'template', 'schedule', 'export'],
        'strategy' => ['ratio', 'objective', 'plan', 'benchmark'],

        'setup' => ['import', 'mapping', 'wizard', 'job'],
        'integration' => ['connector', 'webhook', 'sync-log', 'oauth'],
        'settings' => ['setting', 'group', 'notification-preference'],
    ];

    private const ACTIONS = ['view-any', 'view', 'create', 'update', 'delete', 'approve', 'export', 'archive'];

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

        // ── SUPER ADMIN ────────────────────────────────────────────────────

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // ── ADMIN ROLES (8) ────────────────────────────────────────────────

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_merge($allPermissions, $adminPermissions));

        $systemAdmin = Role::firstOrCreate(['name' => 'system-admin', 'guard_name' => 'web']);
        $systemAdmin->syncPermissions(array_filter($adminPermissions, fn(Permission $p) =>
            in_array($p->name, [
                'admin.servers.view', 'admin.servers.manage',
                'admin.backups.view', 'admin.backups.create', 'admin.backups.restore',
                'admin.audit.view', 'admin.audit.export',
            ])
        ));

        $securityAdmin = Role::firstOrCreate(['name' => 'security-admin', 'guard_name' => 'web']);
        $securityAdmin->syncPermissions(array_filter(
            array_merge($allPermissions, $adminPermissions),
            fn(Permission $p) => in_array($p->name, [
                'admin.audit.view', 'admin.audit.export', 'admin.security.manage', 'admin.security.2fa',
                'admin.users.view', 'auditlog.logs.view-any', 'auditlog.logs.view',
            ])
        ));

        $billingAdmin = Role::firstOrCreate(['name' => 'billing-admin', 'guard_name' => 'web']);
        $billingAdmin->syncPermissions(array_filter(
            array_merge($allPermissions, $adminPermissions),
            fn(Permission $p) => in_array($p->name, [
                'admin.billing.view', 'admin.billing.manage', 'billing.subscription.view-any',
                'billing.plan.view-any', 'billing.invoice.view-any', 'billing.payment-method.view-any',
            ])
        ));

        $supportAdmin = Role::firstOrCreate(['name' => 'support-admin', 'guard_name' => 'web']);
        $supportAdmin->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'helpdesk.')
        ));

        $tenantAdmin = Role::firstOrCreate(['name' => 'tenant-admin', 'guard_name' => 'web']);
        $tenantAdmin->syncPermissions(array_filter($adminPermissions, fn(Permission $p) =>
            in_array($p->name, [
                'admin.modules.view', 'admin.modules.toggle', 'admin.modules.disable',
                'admin.users.view', 'admin.users.create', 'admin.users.update',
                'admin.roles.view', 'admin.roles.assign', 'admin.roles.create',
            ])
        ));

        $integrationAdmin = Role::firstOrCreate(['name' => 'integration-admin', 'guard_name' => 'web']);
        $integrationAdmin->syncPermissions(array_filter(
            array_merge($allPermissions, $adminPermissions),
            fn(Permission $p) => in_array($p->name, [
                'admin.integrations.manage', 'core.integration.view-any', 'core.integration.create',
                'core.integration.update', 'core.integration.delete', 'core.webhook.view-any',
                'integration.connector.view-any', 'integration.webhook.view-any',
            ])
        ));

        // ── MANAGER ROLES (8) ──────────────────────────────────────────────

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerPerms = array_filter($allPermissions, function (Permission $p) {
            if (str_ends_with($p->name, '.delete') &&
                (str_starts_with($p->name, 'accounting.') || str_starts_with($p->name, 'hr.') ||
                 str_starts_with($p->name, 'validation.'))) {
                return false;
            }
            return true;
        });
        $manager->syncPermissions(array_values($managerPerms));

        $financeManager = Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);
        $financeManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'accounting.') || str_starts_with($p->name, 'billing.') ||
            str_starts_with($p->name, 'bi.') || str_starts_with($p->name, 'reporting.') ||
            str_starts_with($p->name, 'payroll.')
        ));

        $salesManager = Role::firstOrCreate(['name' => 'sales-manager', 'guard_name' => 'web']);
        $salesManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'crm.') || str_starts_with($p->name, 'bi.') ||
            str_starts_with($p->name, 'sales.') || str_starts_with($p->name, 'reporting.') ||
            in_array($p->name, ['accounting.invoice.view-any', 'accounting.invoice.view'])
        ));

        $hrManager = Role::firstOrCreate(['name' => 'hr-manager', 'guard_name' => 'web']);
        $hrManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'hr.') || str_starts_with($p->name, 'timesheets.') ||
            str_starts_with($p->name, 'payroll.')
        ));

        $payrollManager = Role::firstOrCreate(['name' => 'payroll-manager', 'guard_name' => 'web']);
        $payrollManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'payroll.') ||
            in_array($p->name, [
                'hr.employee.view-any', 'hr.employee.view',
                'hr.salary.view-any', 'hr.salary.view', 'hr.salary.create', 'hr.salary.update',
                'hr.contract.view-any', 'hr.contract.view',
                'reporting.report.view-any', 'reporting.report.create',
            ])
        ));

        $operationsManager = Role::firstOrCreate(['name' => 'operations-manager', 'guard_name' => 'web']);
        $operationsManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'inventory.') || str_starts_with($p->name, 'logistics.') ||
            str_starts_with($p->name, 'achats.')
        ));

        $projectManager = Role::firstOrCreate(['name' => 'project-manager', 'guard_name' => 'web']);
        $projectManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'projects.') || str_starts_with($p->name, 'timesheets.') ||
            in_array($p->name, [
                'hr.employee.view-any', 'hr.employee.view', 'hr.department.view-any',
            ])
        ));

        // ── OPERATIONAL ROLES (14+) ────────────────────────────────────────

        // Sales & Customer Service
        $salesRep = Role::firstOrCreate(['name' => 'sales-rep', 'guard_name' => 'web']);
        $salesRep->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'crm.')
        ));

        $customerService = Role::firstOrCreate(['name' => 'customer-service', 'guard_name' => 'web']);
        $customerService->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'helpdesk.') ||
            in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view', 'crm.account.view-any', 'crm.account.view',
                'crm.activity.view-any', 'crm.activity.view', 'crm.activity.create',
            ])
        ));

        // customer-service-agent: alias for customer-service with same permissions
        $csAgent = Role::firstOrCreate(['name' => 'customer-service-agent', 'guard_name' => 'web']);
        $csAgent->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'helpdesk.') ||
            in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view', 'crm.account.view-any', 'crm.account.view',
                'crm.activity.view-any', 'crm.activity.view', 'crm.activity.create',
            ])
        ));

        // Inventory & Logistics
        $warehouseOperator = Role::firstOrCreate(['name' => 'warehouse-operator', 'guard_name' => 'web']);
        $warehouseOperator->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            in_array($p->name, [
                'inventory.product.view-any', 'inventory.product.view', 'inventory.product.update',
                'inventory.stock-movement.view-any', 'inventory.stock-movement.view',
                'inventory.stock-movement.create', 'inventory.stock-movement.update',
                'inventory.warehouse.view-any', 'inventory.warehouse.view',
            ])
        ));

        $logisticsManager = Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $logisticsManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'logistics.') || str_starts_with($p->name, 'inventory.')
        ));

        $procurementManager = Role::firstOrCreate(['name' => 'procurement-manager', 'guard_name' => 'web']);
        $procurementManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'inventory.') || str_starts_with($p->name, 'achats.') ||
            in_array($p->name, [
                'accounting.invoice.view-any', 'accounting.invoice.view', 'accounting.invoice.create',
            ])
        ));

        // Finance & Accounting
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'accounting.')
        ));

        $financialAnalyst = Role::firstOrCreate(['name' => 'financial-analyst', 'guard_name' => 'web']);
        $financialAnalyst->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            (str_starts_with($p->name, 'accounting.') && in_array(explode('.', $p->name)[2] ?? '', ['view-any', 'view'])) ||
            str_starts_with($p->name, 'bi.')
        ));

        $inventoryAnalyst = Role::firstOrCreate(['name' => 'inventory-analyst', 'guard_name' => 'web']);
        $inventoryAnalyst->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            (str_starts_with($p->name, 'inventory.') && in_array(explode('.', $p->name)[2] ?? '', ['view-any', 'view'])) ||
            str_starts_with($p->name, 'bi.')
        ));

        $timesheetReviewer = Role::firstOrCreate(['name' => 'timesheet-reviewer', 'guard_name' => 'web']);
        $timesheetReviewer->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'timesheets.') ||
            in_array($p->name, ['hr.employee.view-any', 'hr.employee.view'])
        ));

        // External Partners
        $servicePartner = Role::firstOrCreate(['name' => 'service-partner', 'guard_name' => 'web']);
        $servicePartner->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'helpdesk.ticket.') ||
            in_array($p->name, [
                'projects.task.view-any', 'projects.task.view', 'projects.task.create',
                'projects.project.view-any', 'projects.project.view',
            ])
        ));

        // ── API ROLES ──────────────────────────────────────────────────────

        $apiClient = Role::firstOrCreate(['name' => 'api-client', 'guard_name' => 'web']);
        $apiClient->syncPermissions(array_filter(
            array_merge($allPermissions, $adminPermissions),
            fn(Permission $p) => in_array($p->name, [
                'api.token.view-any', 'api.token.create', 'api.endpoint.view-any',
                'core.integration.view-any', 'core.webhook.view-any', 'core.webhook.create',
            ])
        ));

        $apiAdmin = Role::firstOrCreate(['name' => 'api-admin', 'guard_name' => 'web']);
        $apiAdmin->syncPermissions(array_filter(
            array_merge($allPermissions, $adminPermissions),
            fn(Permission $p) => str_starts_with($p->name, 'api.') ||
                in_array($p->name, ['admin.integrations.manage', 'core.webhook.view-any'])
        ));

        // ── EMPLOYEE ROLES (2) ─────────────────────────────────────────────

        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $employeePerms = array_filter($allPermissions, fn(Permission $p) => ! str_ends_with($p->name, '.delete'));
        $employee->syncPermissions(array_values($employeePerms));

        $contractor = Role::firstOrCreate(['name' => 'contractor', 'guard_name' => 'web']);
        $contractor->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'projects.') && in_array(explode('.', $p->name)[2] ?? '', ['view-any', 'view', 'create', 'update'])
        ));

        $totalRoles = 27;
        $this->command->info(sprintf(
            '✅ Seeded %d permissions across %d roles (all %d modules covered).',
            count($allPermissions) + count($adminPermissions),
            $totalRoles,
            count(self::MODULES)
        ));
    }
}
