<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the ERP role hierarchy and granular permissions.
 *
 * Life MDG scope: CORE platform + Compta/Finance (Accounting), Commercial/CRM
 * (CRM, Sales), Stock/Logistique (Inventory, Logistics, Achats), Pilotage/
 * Reporting (BI, Analytics, Reporting, Strategy), RH basique (HR, Payroll,
 * Timesheets, Projects), Helpdesk. Roles tied exclusively to out-of-scope
 * modules (POS, Manufacturing, Ecommerce, Documents, Email, WhatsApp) have
 * been removed rather than left dangling.
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
 *   tenant-admin         — module toggle and user/role management
 *   — operational roles —
 *   support-agent        — frontline Helpdesk: view/update assigned tickets only
 *   logistics-manager    — full Inventory + Logistics
 *   service-partner      — external service provider: Helpdesk, Projects
 *   purchasing-manager   — procurement: Inventory + Achats + supplier PO management
 *   warehouse-operator   — physical warehouse: products, stock movements
 *   sales-manager        — CRM full + BI dashboards and reports
 *   project-manager      — full Projects module + HR employee view
 *   finance-manager      — full Accounting + BI
 *   customer-service     — full Helpdesk + CRM contact/account view
 *   inventory-analyst    — read-only Inventory + BI analytics
 *   payroll-officer       — full Payroll + HR compensation/leave
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

    // Analytics policies (Modules/Analytics/app/Policies/*) gate on these exact
    // strings with per-resource verbs, not the generic view-any/view/create/
    // update/delete set MODULES/ACTIONS below produces — so they're listed
    // explicitly here, the same way ADMIN_PERMISSIONS is.
    private const ANALYTICS_PERMISSIONS = [
        'analytics.ab_test.view', 'analytics.ab_test.create', 'analytics.ab_test.start',
        'analytics.ab_test.complete', 'analytics.ab_test.deploy', 'analytics.ab_test.delete',
        'analytics.anomaly.view', 'analytics.anomaly.create', 'analytics.anomaly.update',
        'analytics.anomaly.configure', 'analytics.anomaly.delete', 'analytics.anomaly.investigate',
        'analytics.anomaly.resolve', 'analytics.anomaly.dismiss',
        'analytics.ml_model.view', 'analytics.ml_model.create', 'analytics.ml_model.update',
        'analytics.ml_model.deploy', 'analytics.ml_model.rollback', 'analytics.ml_model.delete',
        'analytics.prediction.view', 'analytics.prediction.create', 'analytics.prediction.update',
        'analytics.prediction.train', 'analytics.prediction.delete',
        'analytics.recommendation.view', 'analytics.recommendation.create', 'analytics.recommendation.update',
        'analytics.recommendation.train', 'analytics.recommendation.delete',
        'analytics.recommendation.act', 'analytics.recommendation.dismiss',
    ];

    // TaxComplianceReportPolicy/RevenueContractPolicy/ConsolidationHierarchyPolicy check
    // non-standard verbs the generic MODULES/ACTIONS loop below doesn't produce (file,
    // recognize, restore, force_delete) — same reason ANALYTICS_PERMISSIONS exists above.
    private const ACCOUNTING_EXTRA_PERMISSIONS = [
        'accounting.tax_compliance.file', 'accounting.tax_compliance.restore', 'accounting.tax_compliance.force_delete',
        'accounting.revenue_recognition.recognize', 'accounting.revenue_recognition.restore', 'accounting.revenue_recognition.force_delete',
        'accounting.consolidation.restore', 'accounting.consolidation.force_delete',
        'accounting.depreciation.record', 'accounting.depreciation.restore', 'accounting.depreciation.force_delete',
        'accounting.intercompany.clear', 'accounting.intercompany.restore', 'accounting.intercompany.force_delete',
    ];

    // Modules\Settings\Policies\SettingPolicy checks flat settings.{view,create,update,
    // delete} (no resource segment), not the settings.setting.*/settings.group.* the
    // generic MODULES/ACTIONS loop produces for the 'settings' entry below -- same
    // reasoning as the other _PERMISSIONS constants above.
    private const SETTINGS_PERMISSIONS = [
        'settings.view', 'settings.create', 'settings.update', 'settings.delete',
    ];

    // Modules\HR\Http\Controllers\Api\DocumentAlertController gates every action on a
    // bare $this->authorize('hr.documents.<verb>') ability string (no Policy class) --
    // resolved by Spatie's register_permission_check_method Gate::before hook, so these
    // just need to exist as real permissions, same as the other _PERMISSIONS constants.
    // 'edit'/'remind' aren't in the generic ACTIONS set, and 'documents' isn't in
    // MODULES['hr'] at all.
    private const HR_EXTRA_PERMISSIONS = [
        'hr.documents.view', 'hr.documents.create', 'hr.documents.edit',
        'hr.documents.delete', 'hr.documents.remind',
    ];

    // Modules\Sales\Http\Controllers\Api\{SalesController,SalesAiAssistController}
    // gate every action on flat sales.{read,view,create,update} (no resource
    // segment), not the sales.order.*/sales.line.*/sales.quotation.* the generic
    // MODULES/ACTIONS loop produces for the 'sales' entry below -- same reasoning
    // as SETTINGS_PERMISSIONS. Both 'read' (list/show) and 'view' (AI-assist gate)
    // exist because the controllers were written independently and never agreed
    // on one verb.
    private const SALES_PERMISSIONS = [
        'sales.read', 'sales.view', 'sales.create', 'sales.update',
    ];

    // Modules\Security\Policies\{SecurityIncident,ComplianceAudit,ComplianceControl,
    // EncryptionKey,ThreatIndicator}Policy check security.{resource}.{view,create,update,
    // delete} -- the generic ACTIONS list already produces those (plus an unused
    // .view-any) once 'security' is added to MODULES below, so no extra const is
    // needed for the standard verbs. EncryptionKeyPolicy::rotate() checks the one
    // non-standard verb, security.encryption.rotate.
    private const SECURITY_EXTRA_PERMISSIONS = [
        'security.encryption.rotate',
    ];

    private const MODULES = [
        'crm'              => ['contact', 'lead', 'opportunity', 'account', 'activity', 'pipeline'],
        'security'         => ['incident', 'audit', 'compliance', 'encryption', 'threat'],
        'sales'            => ['order', 'line', 'quotation'],
        'hr'               => ['employee', 'department', 'job-position', 'leave', 'leave-type'],
        'payroll'          => ['payslip', 'run', 'tax-config'],
        'timesheets'       => ['timesheet', 'entry'],
        'projects'         => ['project', 'task'],
        'inventory'        => ['product', 'category', 'warehouse', 'unit', 'stock-movement', 'purchase-order', 'supplier'],
        'logistics'        => ['shipment', 'route', 'carrier', 'customs-declaration'],
        'achats'           => ['rfq', 'purchase-order', 'purchase-receipt', 'supplier'],
        'accounting'       => ['invoice', 'journal', 'chart-of-account', 'tax_compliance', 'revenue_recognition', 'consolidation', 'depreciation', 'intercompany'],
        'helpdesk'         => ['ticket', 'team'],
        'bi'               => ['dashboard', 'kpi', 'report'],
        'analytics'        => ['forecast', 'anomaly'],
        'reporting'        => ['report', 'template', 'schedule'],
        'strategy'         => ['ratio', 'objective', 'plan'],
        'auditlog'         => ['logs'],
        'setup'            => ['import', 'mapping', 'wizard'],
        'integration'      => ['connector', 'webhook', 'sync-log'],
        'settings'         => ['setting', 'group'],
        'validation'       => ['workflow', 'rule', 'hierarchy', 'request'],
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

        // Analytics' policies use per-resource verbs the generic loop above
        // doesn't produce (investigate, resolve, deploy, rollback, train, ...) —
        // merged into $allPermissions so admin/manager/employee/inventory-analyst
        // (which already filters on the 'analytics.' prefix below) pick them up.
        foreach (self::ANALYTICS_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Same reasoning as ANALYTICS_PERMISSIONS above, for Accounting's
        // tax_compliance/revenue_recognition/consolidation policies.
        foreach (self::ACCOUNTING_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::SETTINGS_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::HR_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::SALES_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::SECURITY_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
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
            // No delete on invoices/employees from managers
            if (str_ends_with($p->name, '.delete') &&
                (str_starts_with($p->name, 'accounting.') || str_starts_with($p->name, 'hr.'))) {
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

        // hr-manager: full access to HR + Payroll + Timesheets
        $hrManager = Role::firstOrCreate(['name' => 'hr-manager', 'guard_name' => 'web']);
        $hrManager->syncPermissions(
            array_filter($allPermissions, fn(Permission $p) =>
                str_starts_with($p->name, 'hr.') ||
                str_starts_with($p->name, 'payroll.') ||
                str_starts_with($p->name, 'timesheets.')
            )
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

        // security-admin: audit logs, 2FA policy, sessions, API keys, and the whole
        // Security module (incident/audit/compliance/encryption/threat registers)
        $securityAdmin = Role::firstOrCreate(['name' => 'security-admin', 'guard_name' => 'web']);
        $securityAdmin->syncPermissions(array_filter(
            $allPermissions,
            fn(Permission $p) => in_array($p->name, [
                'admin.audit.view', 'admin.security.manage', 'admin.users.view',
                'auditlog.logs.view-any', 'auditlog.logs.view',
            ]) || str_starts_with($p->name, 'security.')
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

        // support-agent: frontline Helpdesk agent — view/create/update tickets
        // assigned to them (TicketPolicy enforces the assignment scoping itself
        // via hasRole('support-agent'), this permission set is deliberately
        // narrower than support-admin's — no delete, no team management).
        $supportAgent = Role::firstOrCreate(['name' => 'support-agent', 'guard_name' => 'web']);
        $supportAgent->syncPermissions(array_filter(
            $allPermissions,
            fn(Permission $p) => str_starts_with($p->name, 'helpdesk.ticket.') && ! str_ends_with($p->name, '.delete')
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

        // ── Operational roles ───────────────────────────────────────────────

        // logistics-manager: full Inventory + Logistics
        $logisticsManager = Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $logisticsManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'inventory.') ||
            str_starts_with($p->name, 'logistics.')
        ));

        // service-partner: external service provider (Helpdesk tickets, Projects tasks)
        $servicePartner = Role::firstOrCreate(['name' => 'service-partner', 'guard_name' => 'web']);
        $servicePartner->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'helpdesk.ticket.') ||
            in_array($p->name, [
                'projects.task.view-any', 'projects.task.view', 'projects.task.create', 'projects.task.update',
                'projects.project.view-any', 'projects.project.view',
            ])
        ));

        // purchasing-manager: procurement (Inventory + Achats + Accounting invoices)
        $purchasingManager = Role::firstOrCreate(['name' => 'purchasing-manager', 'guard_name' => 'web']);
        $purchasingManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'inventory.') ||
            str_starts_with($p->name, 'achats.') ||
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

        // sales-manager: CRM + Sales full access + BI reports/dashboards
        $salesManager = Role::firstOrCreate(['name' => 'sales-manager', 'guard_name' => 'web']);
        $salesManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'crm.') ||
            str_starts_with($p->name, 'sales.') ||
            in_array($p->name, [
                'bi.dashboard.view-any', 'bi.dashboard.view',
                'bi.report.view-any', 'bi.report.view',
                'bi.kpi.view-any', 'bi.kpi.view',
                'accounting.invoice.view-any', 'accounting.invoice.view',
            ])
        ));

        // project-manager: full Projects + Timesheets + HR employee view
        $projectManager = Role::firstOrCreate(['name' => 'project-manager', 'guard_name' => 'web']);
        $projectManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'projects.') ||
            str_starts_with($p->name, 'timesheets.') ||
            in_array($p->name, [
                'hr.employee.view-any', 'hr.employee.view',
                'hr.department.view-any', 'hr.department.view',
            ])
        ));

        // finance-manager: full Accounting + full BI + Strategy
        $financeManager = Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);
        $financeManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'accounting.') ||
            str_starts_with($p->name, 'bi.') ||
            str_starts_with($p->name, 'strategy.')
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

        // inventory-analyst: read-only Inventory + full BI/Analytics
        $inventoryAnalyst = Role::firstOrCreate(['name' => 'inventory-analyst', 'guard_name' => 'web']);
        $inventoryAnalyst->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            (str_starts_with($p->name, 'inventory.') && in_array(explode('.', $p->name)[2] ?? '', ['view-any', 'view'])) ||
            str_starts_with($p->name, 'bi.') ||
            str_starts_with($p->name, 'analytics.')
        ));

        // payroll-officer: full Payroll + HR compensation/leave
        $payrollOfficer = Role::firstOrCreate(['name' => 'payroll-officer', 'guard_name' => 'web']);
        $payrollOfficer->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'payroll.') ||
            str_starts_with($p->name, 'hr.')
        ));

        // approver: can view/action approval requests assigned to them, across
        // every module that raises one (Achats POs, Accounting invoices, ...).
        // Referenced by ApprovalRequestPolicy but was never seeded before this.
        $approver = Role::firstOrCreate(['name' => 'approver', 'guard_name' => 'web']);
        $approver->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'validation.request.')
        ));

        $totalRoles = 24;
        $this->command->info(sprintf(
            'Seeded %d permissions across %d roles.',
            count($allPermissions) + count($adminPermissions),
            $totalRoles
        ));
    }
}
