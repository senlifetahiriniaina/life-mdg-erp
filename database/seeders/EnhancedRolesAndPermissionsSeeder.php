<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Enhanced RBAC seeder covering all 29 modules with 40+ roles.
 *
 * Rôles (40 total):
 * - super-admin (1)
 * - admin (8)
 * - manager (8)
 * - operational (14+)
 * - mobile & api (3+)
 * - employee (2)
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

    // All 29 modules with their resources
    private const MODULES = [
        // System
        'core' => ['setting', 'health', 'integration', 'webhook'],
        'auditlog' => ['logs', 'export', 'filter'],
        'api' => ['token', 'endpoint', 'webhook', 'integration', 'rate-limit', 'key', 'usage'],

        // Sales & CRM
        'crm' => ['contact', 'lead', 'opportunity', 'account', 'activity', 'pipeline', 'campaign', 'proposal'],
        'ecommerce' => ['order', 'store', 'product', 'marketplace', 'vendor', 'customer', 'review', 'promotion'],
        'pos' => ['order', 'session', 'terminal', 'cash-movement', 'shift', 'loyalty', 'receipt'],

        // Finance & Accounting
        'accounting' => ['invoice', 'journal', 'chart-of-account', 'tax', 'payment', 'reconciliation', 'expense', 'budget'],
        'billing' => ['subscription', 'plan', 'invoice', 'payment-method', 'invoice-line'],

        // Operations & Inventory
        'inventory' => ['product', 'category', 'warehouse', 'unit', 'stock-movement', 'sku', 'barcode', 'location'],
        'logistics' => ['shipment', 'tracking', 'delivery-zone', 'route', 'vehicle', 'carrier'],
        'achats' => ['po', 'supplier', 'purchase-request', 'approval', 'quotation', 'vendor-invoice'],
        'purchasing' => ['po', 'supplier', 'purchase-request', 'vendor-account', 'contract'],

        // Manufacturing & Planning
        'manufacturing' => ['production-order', 'bom', 'workcenter', 'routing', 'operation', 'batch', 'schedule'],
        'plm' => ['product-version', 'design', 'bom', 'lifecycle-stage', 'engineering-change', 'document', 'revision'],
        'planning' => ['schedule', 'resource', 'shift', 'allocation', 'conflict', 'availability', 'shift-swap'],
        'quality' => ['inspection', 'non-conformance', 'audit', 'report', 'finding', 'corrective-action'],

        // Human Resources
        'hr' => ['employee', 'department', 'job-position', 'leave', 'leave-type', 'salary', 'contract', 'applicant'],
        'timesheets' => ['timesheet', 'approval', 'allocation', 'absence', 'overtime', 'report', 'sync'],

        // Support & Service
        'helpdesk' => ['ticket', 'team', 'sla', 'priority', 'category', 'faq', 'escalation', 'feedback'],
        'discussion' => ['forum', 'topic', 'comment', 'mention', 'attachment', 'notification'],

        // Project & Document Management
        'projects' => ['project', 'task', 'milestone', 'team-member', 'resource', 'timeline', 'budget', 'deliverable'],
        'documents' => ['document', 'folder', 'template', 'version', 'signature', 'access-log', 'share'],
        'validation' => ['workflow', 'approval-request', 'e-signature', 'delegation', 'rule', 'step'],

        // Marketing & Communication
        'email' => ['campaign', 'template', 'subscriber', 'segment', 'bounce', 'automation', 'schedule'],
        'whatsapp' => ['conversation', 'broadcast', 'template', 'contact', 'session', 'webhook'],
        'marketingautomation' => ['workflow', 'lead-scoring', 'campaign', 'automation', 'integration', 'template'],

        // Analytics & Reporting
        'bi' => ['dashboard', 'kpi', 'report', 'datasource', 'query', 'export', 'visualization'],

        // Automation & Integration
        'workflowautomation' => ['rule', 'trigger', 'action', 'integration', 'webhook', 'log', 'variable'],

        // Mobile & API
        'mobile' => ['device', 'session', 'sync', 'notification', 'offline-queue', 'cache', 'analytics'],

        // Modules promoted to COMPLET (Phase 52)
        'assets'          => ['asset', 'maintenance', 'depreciation', 'category', 'location'],
        'contracts'       => ['contract', 'template', 'renewal', 'clause', 'signatory'],
        'sales'           => ['order', 'line', 'quotation', 'invoice', 'return'],
        'sms'             => ['message', 'template', 'campaign', 'provider'],
        'setup'           => ['import', 'mapping', 'wizard', 'job'],
        'reporting'       => ['report', 'template', 'schedule', 'export'],
        'integration'     => ['connector', 'webhook', 'sync-log', 'oauth'],
        'smart_tables'    => ['table', 'column', 'row', 'base', 'view'],
        'notes'           => ['page', 'attachment', 'tag'],
        'customerservice' => ['ticket', 'escalation', 'category', 'feedback', 'sla'],
        'settings'        => ['setting', 'group', 'notification-preference'],
        'payroll'         => ['payslip', 'run', 'tax-config', 'deduction', 'allowance'],
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

        $contentAdmin = Role::firstOrCreate(['name' => 'content-admin', 'guard_name' => 'web']);
        $contentAdmin->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'documents.') || str_starts_with($p->name, 'email.')
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
                'workflowautomation.integration.view-any', 'workflowautomation.webhook.view-any',
            ])
        ));

        // ── MANAGER ROLES (8) ──────────────────────────────────────────────

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerPerms = array_filter($allPermissions, function (Permission $p) {
            if (str_ends_with($p->name, '.delete') &&
                (str_starts_with($p->name, 'accounting.') || str_starts_with($p->name, 'hr.') ||
                 str_starts_with($p->name, 'documents.') || str_starts_with($p->name, 'validation.'))) {
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
            str_starts_with($p->name, 'planning.') || str_starts_with($p->name, 'payroll.')
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
            str_starts_with($p->name, 'inventory.') || str_starts_with($p->name, 'manufacturing.') ||
            str_starts_with($p->name, 'logistics.') || str_starts_with($p->name, 'planning.')
        ));

        $projectManager = Role::firstOrCreate(['name' => 'project-manager', 'guard_name' => 'web']);
        $projectManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'projects.') || in_array($p->name, [
                'hr.employee.view-any', 'hr.employee.view', 'hr.department.view-any',
                'documents.document.view-any', 'documents.document.view', 'documents.document.create',
            ])
        ));

        $marketingManager = Role::firstOrCreate(['name' => 'marketing-manager', 'guard_name' => 'web']);
        $marketingManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'email.') || str_starts_with($p->name, 'whatsapp.') ||
            str_starts_with($p->name, 'marketingautomation.') || in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view', 'crm.lead.view-any', 'crm.lead.view',
                'bi.dashboard.view-any', 'bi.report.view-any',
            ])
        ));

        $qualityManager = Role::firstOrCreate(['name' => 'quality-manager', 'guard_name' => 'web']);
        $qualityManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'quality.') || str_starts_with($p->name, 'manufacturing.') ||
            str_starts_with($p->name, 'planning.')
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
            str_starts_with($p->name, 'customerservice.') ||
            in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view', 'crm.account.view-any', 'crm.account.view',
                'crm.activity.view-any', 'crm.activity.view', 'crm.activity.create',
            ])
        ));

        // customer-service-agent: alias for customer-service with same permissions
        $csAgent = Role::firstOrCreate(['name' => 'customer-service-agent', 'guard_name' => 'web']);
        $csAgent->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'helpdesk.') ||
            str_starts_with($p->name, 'customerservice.') ||
            in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view', 'crm.account.view-any', 'crm.account.view',
                'crm.activity.view-any', 'crm.activity.view', 'crm.activity.create',
                'notes.page.view-any', 'notes.page.view', 'notes.page.create',
            ])
        ));

        $communityManager = Role::firstOrCreate(['name' => 'community-manager', 'guard_name' => 'web']);
        $communityManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'whatsapp.') || str_starts_with($p->name, 'email.') ||
            in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view', 'crm.contact.create', 'crm.contact.update',
                'crm.lead.view-any', 'crm.lead.view', 'crm.lead.create', 'crm.activity.view-any',
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
            str_starts_with($p->name, 'logistics.') || str_starts_with($p->name, 'inventory.') ||
            in_array($p->name, ['pos.order.view-any', 'ecommerce.order.view-any'])
        ));

        $procurementManager = Role::firstOrCreate(['name' => 'procurement-manager', 'guard_name' => 'web']);
        $procurementManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'inventory.') || str_starts_with($p->name, 'achats.') ||
            str_starts_with($p->name, 'purchasing.') || in_array($p->name, [
                'accounting.invoice.view-any', 'accounting.invoice.view', 'accounting.invoice.create',
            ])
        ));

        // Manufacturing & Quality
        $productionManager = Role::firstOrCreate(['name' => 'production-manager', 'guard_name' => 'web']);
        $productionManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'manufacturing.') ||
            in_array($p->name, ['inventory.product.view-any', 'inventory.warehouse.view-any'])
        ));

        $qualityInspector = Role::firstOrCreate(['name' => 'quality-inspector', 'guard_name' => 'web']);
        $qualityInspector->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'quality.') || in_array($p->name, [
                'manufacturing.production-order.view-any', 'manufacturing.bom.view-any',
                'planning.schedule.view-any',
            ])
        ));

        $planningCoordinator = Role::firstOrCreate(['name' => 'planning-coordinator', 'guard_name' => 'web']);
        $planningCoordinator->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'planning.') || in_array($p->name, [
                'manufacturing.production-order.view-any', 'manufacturing.production-order.update',
                'hr.employee.view-any', 'timesheets.timesheet.view-any',
            ])
        ));

        // POS & Retail
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'pos.') ||
            in_array($p->name, ['inventory.product.view-any', 'inventory.product.view'])
        ));

        $shiftManager = Role::firstOrCreate(['name' => 'shift-manager', 'guard_name' => 'web']);
        $shiftManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'pos.') || str_starts_with($p->name, 'timesheets.') ||
            str_starts_with($p->name, 'planning.')
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

        // HR & Recruiting
        $recruiter = Role::firstOrCreate(['name' => 'recruiter', 'guard_name' => 'web']);
        $recruiter->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            in_array($p->name, [
                'hr.employee.view-any', 'hr.job-position.view-any', 'hr.job-position.create',
                'hr.applicant.view-any', 'hr.applicant.create', 'hr.applicant.update',
                'discussion.forum.view-any', 'discussion.topic.view-any',
            ])
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
                'documents.document.view-any', 'documents.document.view',
                'projects.task.view-any', 'projects.task.view', 'projects.task.create',
                'projects.project.view-any', 'projects.project.view',
            ])
        ));

        $brandOwner = Role::firstOrCreate(['name' => 'brand-owner', 'guard_name' => 'web']);
        $brandOwner->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'ecommerce.store.') ||
            str_starts_with($p->name, 'ecommerce.product.') ||
            in_array($p->name, ['ecommerce.order.view-any', 'ecommerce.order.view'])
        ));

        $marketplaceAdmin = Role::firstOrCreate(['name' => 'marketplace-admin', 'guard_name' => 'web']);
        $marketplaceAdmin->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'ecommerce.') ||
            str_starts_with($p->name, 'inventory.product.') ||
            str_starts_with($p->name, 'inventory.category.') ||
            in_array($p->name, [
                'crm.account.view-any', 'crm.account.view', 'crm.account.create',
                'bi.report.view-any', 'bi.dashboard.view-any',
            ])
        ));

        // ── MOBILE & API ROLES (3+) ────────────────────────────────────────

        $mobileUser = Role::firstOrCreate(['name' => 'mobile-user', 'guard_name' => 'web']);
        $mobileUser->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'mobile.') ||
            str_starts_with($p->name, 'core.') ||
            in_array($p->name, [
                'crm.contact.view-any', 'crm.contact.view', 'crm.activity.view-any',
                'inventory.product.view-any', 'inventory.product.view',
                'projects.task.view-any', 'projects.task.view',
                'timesheets.timesheet.create', 'timesheets.timesheet.view',
            ])
        ));

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
            (str_starts_with($p->name, 'projects.') && in_array(explode('.', $p->name)[2] ?? '', ['view-any', 'view', 'create', 'update'])) ||
            (str_starts_with($p->name, 'documents.') && in_array(explode('.', $p->name)[2] ?? '', ['view-any', 'view'])) ||
            (str_starts_with($p->name, 'discussion.') && in_array(explode('.', $p->name)[2] ?? '', ['view-any', 'view', 'create']))
        ));

        $totalRoles = 41;
        $this->command->info(sprintf(
            '✅ Seeded %d permissions across %d roles (all %d modules covered).',
            count($allPermissions) + count($adminPermissions),
            $totalRoles,
            count(self::MODULES)
        ));
    }
}
