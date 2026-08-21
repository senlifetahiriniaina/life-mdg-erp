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

    // TaxComplianceReportPolicy/ConsolidationHierarchyPolicy check non-standard verbs
    // the generic MODULES/ACTIONS loop below doesn't produce (file, restore, force_delete)
    // — same reason ANALYTICS_PERMISSIONS exists above. (ASC606 revenue-recognition
    // permissions removed along with the rest of that excluded feature — see CLAUDE.md.)
    private const ACCOUNTING_EXTRA_PERMISSIONS = [
        'accounting.tax_compliance.file', 'accounting.tax_compliance.restore', 'accounting.tax_compliance.force_delete',
        'accounting.consolidation.restore', 'accounting.consolidation.force_delete',
        'accounting.depreciation.record', 'accounting.depreciation.restore', 'accounting.depreciation.force_delete',
        'accounting.intercompany.clear', 'accounting.intercompany.restore', 'accounting.intercompany.force_delete',
        'accounting.expense.approve',
        'accounting.asset_impairment.approve', 'accounting.asset_impairment.record',
        'accounting.asset_impairment.restore', 'accounting.asset_impairment.force_delete',
        'accounting.depreciation_policy.restore', 'accounting.depreciation_policy.force_delete',
        'accounting.budget_scenario.approve',
        // Chantier 18 — FinancialSimulationPolicy checks a "realize" ability
        // (turn a simulated line into a real order + journal entry), a
        // non-standard verb the generic MODULES/ACTIONS loop doesn't produce.
        'accounting.financial-simulation.realize',
        // Chantier 19 re-verification — new CompanyPolicy (Modules\Accounting\Models\
        // Company, the multi-entity consolidation-group model) checks 3 non-standard
        // verbs the generic loop doesn't produce, reusing the already-seeded
        // accounting.consolidation.* prefix.
        'accounting.consolidation.generate-report',
        'accounting.consolidation.record-transaction',
        'accounting.consolidation.eliminate-intercompany',
        // Chantier 32.14: BudgetManagementController had ZERO authorize() calls on
        // 23 of its 24 methods (only generateFromHistory() was gated, at Chantier
        // 26C) — approveBudget()/rejectBudget() in particular had no check
        // whatsoever, confirmed empirically reachable by any accountant/
        // finance-manager/manager/admin regardless of the budget's real company.
        // 'approve'/'reject' aren't in the generic CRUD-action list the
        // MODULES/ACTIONS loop produces for 'budget' (already seeded there:
        // view-any/view/create/update/delete).
        'accounting.budget.approve',
        'accounting.budget.reject',
    ];

    // Modules\CRM\Policies\{CampaignPolicy,WorkflowPolicy} check crm.{campaigns,workflows}.
    // {view,create,edit,delete} — non-standard "edit" verb (not "update") and no
    // "view-any" (viewAny() checks .view), so the generic MODULES/ACTIONS loop
    // below can't produce these, same reasoning as the other _PERMISSIONS
    // constants. Chantier 8.2 found these policies existed but were never
    // seeded here — the seeder that did seed them (Modules\CRM\database\seeders\
    // PermissionSeeder, via CRMDatabaseSeeder/TenantDefaultSeeder) is never
    // reached from this repo's actual DatabaseSeeder chain.
    // Phase B (Chantier 10): CallRecordingPolicy/RevenueInsightPolicy (new —
    // built during Chantier 10's Accounting+CRM+Sales re-verification pass,
    // see CLAUDE.md) check crm.call-recording.{view,summarize} and
    // crm.revenue-intelligence.{view,create,resolve} — non-standard verbs
    // the generic MODULES/ACTIONS loop can't produce, same reasoning as the
    // rest of this const. Both policies already fail closed via a
    // PermissionDoesNotExist-catching wrapper, so this was not itself an
    // active vulnerability — but it means finance-manager/sales-manager/
    // admin/super-admin (the roles meant to actually use these features)
    // were silently denied everywhere until these strings existed.
    private const CRM_EXTRA_PERMISSIONS = [
        'crm.campaigns.view', 'crm.campaigns.create', 'crm.campaigns.edit', 'crm.campaigns.delete',
        'crm.workflows.view', 'crm.workflows.create', 'crm.workflows.edit', 'crm.workflows.delete',
        'crm.call-recording.view', 'crm.call-recording.summarize',
        'crm.revenue-intelligence.view', 'crm.revenue-intelligence.create', 'crm.revenue-intelligence.resolve',
    ];

    // Modules\Settings\Policies\SettingPolicy checks flat settings.{view,create,update,
    // delete} (no resource segment), not the settings.setting.* the generic
    // MODULES/ACTIONS loop produces for the 'settings' entry below -- same
    // reasoning as the other _PERMISSIONS constants above. (Chantier 32.9:
    // 'group' was removed from that entry's resource list along with the now-
    // deleted SettingGroup model, so it only ever generates settings.setting.*
    // today, not settings.group.* too.)
    private const SETTINGS_PERMISSIONS = [
        'settings.view', 'settings.create', 'settings.update', 'settings.delete',
    ];

    // Modules\HR\Http\Controllers\Api\DocumentAlertController gates every action on a
    // bare $this->authorize('hr.documents.<verb>') ability string (no Policy class) --
    // resolved by Spatie's register_permission_check_method Gate::before hook, so these
    // just need to exist as real permissions, same as the other _PERMISSIONS constants.
    // 'edit'/'remind' aren't in the generic ACTIONS set, and 'documents' isn't in
    // MODULES['hr'] at all.
    // Chantier 8.3 (HR): 'salary-band'/'skill' aren't in MODULES['hr'] at all —
    // DepartmentController/JobPositionController/LeaveTypeController/SalaryBandController/
    // SkillController had zero authorize() calls and (for salary-band/skill) no Policy
    // class of any kind, a live RBAC hole identical to the one fixed for Inventory earlier
    // in this chantier. Department/job-position/leave/leave-type already had permission
    // strings seeded via the generic MODULES loop; only salary-band/skill needed adding here.
    private const HR_EXTRA_PERMISSIONS = [
        'hr.documents.view', 'hr.documents.create', 'hr.documents.edit',
        'hr.documents.delete', 'hr.documents.remind',
        'hr.salary-band.view-any', 'hr.salary-band.view', 'hr.salary-band.create',
        'hr.salary-band.update', 'hr.salary-band.delete',
        'hr.skill.view-any', 'hr.skill.view', 'hr.skill.create',
        'hr.skill.update', 'hr.skill.delete',
        // Chantier 8.3: AttendancePolicy's 13 abilities (backing
        // AttendanceBiometricController, now routed for the first time) check
        // these exact permission strings — none were seeded before.
        'hr.attendance.view', 'hr.attendance.view-personal', 'hr.attendance.record',
        'hr.attendance.manage-devices', 'hr.attendance.verify-records',
        'hr.attendance.handle-exceptions', 'hr.attendance.approve-exception',
        'hr.attendance.request-time-off', 'hr.attendance.approve-time-off',
        'hr.attendance.reject-time-off', 'hr.attendance.manage-shifts',
        'hr.attendance.view-analytics', 'hr.attendance.export',
    ];

    // Chantier 8.2 (BI): AlertRule/DataStory/ExternalDataSource/ForecastModel/
    // CustomVisualization were 5 fully-written subsystems (policy + controller +
    // authorize() calls already in place) that were never wired to a route or a
    // seeded permission. Each uses its own 'bi.<resource>.' prefix so there is no
    // collision across the 5 groups below.
    private const BI_EXTRA_PERMISSIONS = [
        // AlertRule
        'bi.alert.view-any', 'bi.alert.view', 'bi.alert.create', 'bi.alert.update', 'bi.alert.delete',
        'bi.alert.acknowledge', 'bi.alert.manage-rules', 'bi.alert.escalate', 'bi.alert.view-history',
        // DataStory
        'bi.datastory.view-any', 'bi.datastory.view', 'bi.datastory.create', 'bi.datastory.update', 'bi.datastory.delete',
        'bi.datastory.publish', 'bi.datastory.share', 'bi.datastory.manage-narratives', 'bi.datastory.view-analytics',
        // ExternalDataSource
        'bi.externaldata.view-any', 'bi.externaldata.view', 'bi.externaldata.create', 'bi.externaldata.connect',
        'bi.externaldata.disconnect', 'bi.externaldata.manage-credentials', 'bi.externaldata.configure-sync',
        'bi.externaldata.transform-data', 'bi.externaldata.view-history', 'bi.externaldata.delete',
        // Forecasting (ForecastModel)
        'bi.forecasting.view-any', 'bi.forecasting.view', 'bi.forecasting.create', 'bi.forecasting.train',
        'bi.forecasting.deploy', 'bi.forecasting.delete', 'bi.forecasting.archive', 'bi.forecasting.view-predictions',
        'bi.forecasting.manage-scenarios', 'bi.forecasting.view-accuracy',
        // Visualization (CustomVisualization)
        'bi.visualization.view-any', 'bi.visualization.view', 'bi.visualization.create', 'bi.visualization.update',
        'bi.visualization.delete', 'bi.visualization.export', 'bi.visualization.share',
        // Phase B (Chantier 10): BiAlertPolicy/BiAnomalyPolicy/BiDataSourcePolicy
        // (distinct legacy policy classes from AlertPolicy/ForecastingPolicy/
        // ExternalDataPolicy above — target the separate BiAlert/BiAnomaly/
        // BiDataSource models behind AlertController/PredictiveAnalyticsController/
        // DataSourceController) check bi.bialert.*/bi.bianomaly.*/bi.bidatasource.*
        // with approve/export/archive verbs the generic MODULES/ACTIONS loop
        // doesn't produce for 'bidatasource' (already in MODULES['bi']) and
        // doesn't produce at all for 'bialert'/'bianomaly' (not in MODULES['bi']).
        'bi.bialert.view-any', 'bi.bialert.view', 'bi.bialert.create', 'bi.bialert.update',
        'bi.bialert.delete', 'bi.bialert.approve', 'bi.bialert.export', 'bi.bialert.archive',
        'bi.bianomaly.view-any', 'bi.bianomaly.view', 'bi.bianomaly.create', 'bi.bianomaly.update',
        'bi.bianomaly.delete', 'bi.bianomaly.approve', 'bi.bianomaly.export', 'bi.bianomaly.archive',
        'bi.bidatasource.approve', 'bi.bidatasource.export', 'bi.bidatasource.archive',
    ];

    // Modules\Helpdesk\Policies\CustomerServiceAIPolicy backs CustomerServiceAIController's
    // 21 cs-ai endpoints (sentiment/emotion/language analysis, routing rules, escalation
    // predictions, response templates/suggestions, satisfaction/NPS predictions, agent
    // performance/coaching/goals) with 44 distinct helpdesk.{resource}.{verb} strings that
    // don't match the generic MODULES/ACTIONS loop's helpdesk resources (ticket, team,
    // agent-performance) at all — same reasoning as the other _PERMISSIONS constants.
    // Chantier 8.2 found the policy existed but was never wired into the controller nor
    // seeded here.
    private const HELPDESK_EXTRA_PERMISSIONS = [
        'helpdesk.sentiment.view', 'helpdesk.sentiment.manage', 'helpdesk.sentiment.deploy',
        'helpdesk.emotion.view',
        'helpdesk.language.view', 'helpdesk.language.manage',
        'helpdesk.routing.view', 'helpdesk.routing.create', 'helpdesk.routing.update', 'helpdesk.routing.delete',
        'helpdesk.escalation.view', 'helpdesk.escalation.manage', 'helpdesk.escalation.deploy',
        'helpdesk.urgency.view', 'helpdesk.escalation.manage-workflows', 'helpdesk.escalation.execute',
        'helpdesk.response.view', 'helpdesk.response.create', 'helpdesk.response.update', 'helpdesk.response.delete',
        'helpdesk.response.variants.view', 'helpdesk.response.variants.generate',
        'helpdesk.response.suggestions.view', 'helpdesk.response.performance.view',
        'helpdesk.satisfaction.view', 'helpdesk.satisfaction.manage', 'helpdesk.satisfaction.deploy',
        'helpdesk.nps.view', 'helpdesk.satisfaction.manage-factors',
        'helpdesk.agent-metrics.view', 'helpdesk.team-metrics.view',
        'helpdesk.agent-trends.view', 'helpdesk.agent-skills.view', 'helpdesk.agent-skills.manage',
        'helpdesk.coaching.view', 'helpdesk.coaching.create', 'helpdesk.coaching.update',
        'helpdesk.benchmarking.view', 'helpdesk.benchmarking.generate',
        'helpdesk.goals.view', 'helpdesk.goals.create', 'helpdesk.goals.update', 'helpdesk.goals.delete',
        'helpdesk.ai.export', 'helpdesk.ai.reports', 'helpdesk.ai.configure',
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
    // ComplianceViolation,EncryptionKey,ThreatIndicator,TrustZone,ServiceIdentity}Policy
    // check security.{resource}.{view,create,update,delete} -- the generic ACTIONS list
    // already produces those (plus an unused .view-any) once 'security' is added to
    // MODULES below, so no extra const is needed for the standard verbs.
    // EncryptionKeyPolicy::rotate()/ServiceIdentityPolicy::rotate() check the one
    // non-standard verb each, security.{encryption,identity}.rotate.
    private const SECURITY_EXTRA_PERMISSIONS = [
        'security.encryption.rotate',
        'security.identity.rotate',
    ];

    // Modules\Payroll\Http\Controllers\Api\PayrollController checks
    // payroll.payslip.{view,generate,approve} -- 'view' already exists from the
    // generic MODULES/ACTIONS loop ('payslip' is in MODULES['payroll']), but
    // 'generate'/'approve' aren't standard CRUD verbs, same reasoning as the
    // other _PERMISSIONS constants above.
    private const PAYROLL_EXTRA_PERMISSIONS = [
        'payroll.payslip.generate', 'payroll.payslip.approve',
    ];

    // Modules\AuditLog\Http\Controllers\Api\{AuditLogApiController,
    // AuditLogAiAssistController} check auditlog.logs.export -- 'view' already
    // exists from the generic MODULES/ACTIONS loop ('logs' is in
    // MODULES['auditlog']), but 'export' isn't a standard CRUD verb, same
    // reasoning as the other _PERMISSIONS constants above.
    private const AUDITLOG_EXTRA_PERMISSIONS = [
        'auditlog.logs.export',
    ];

    // Chantier 8.3 (Core): CustomFieldPolicy checks
    // core.customfield.{view-any,view,create,update,delete,approve,export,
    // archive} -- 'core' isn't in MODULES at all (unlike security/payroll/
    // auditlog above, which only needed one or two extra non-standard
    // verbs), so every verb needs to be listed here explicitly.
    // (The sibling core.approvalworkflow.* block that used to live here was
    // removed at Chantier 32.1 along with the confirmed-dead Core Approval
    // engine it gated — ApprovalWorkflowPolicy/ApprovalController/
    // ApprovalService/ApprovalWorkflow/ApprovalInstance/ApprovalDecision —
    // see CLAUDE.md's Chantier 32.1 entry.)
    // Chantier 8.3 (Logistics): DeliveryRoundPolicy checks
    // logistics.deliveryround.{view-any,view,create,update,delete,approve,
    // export,archive} -- 'deliveryround' isn't in MODULES['logistics'] at all
    // (only shipment/route/carrier/customs-declaration), so every verb needs
    // to be listed here explicitly, same reasoning as CORE_EXTRA_PERMISSIONS.
    private const LOGISTICS_EXTRA_PERMISSIONS = [
        'logistics.deliveryround.view-any', 'logistics.deliveryround.view', 'logistics.deliveryround.create',
        'logistics.deliveryround.update', 'logistics.deliveryround.delete', 'logistics.deliveryround.approve',
        'logistics.deliveryround.export', 'logistics.deliveryround.archive',
    ];

    // Phase B (Chantier 10 — Stock+Logistique group): WarehousePolicy/
    // StockMovementPolicy/StockPolicy were fully written but Inventory had
    // zero registerPolicies() call and zero authorize() calls anywhere
    // (Chantier 10 fixed the wiring) — 'stock' isn't in MODULES['inventory']
    // at all (only 'stock-movement'/'warehouse'), and all three policies'
    // approve/export/archive verbs aren't in the generic ACTIONS list, same
    // reasoning as the other _EXTRA_PERMISSIONS constants.
    private const INVENTORY_EXTRA_PERMISSIONS = [
        'inventory.stock.view-any', 'inventory.stock.view', 'inventory.stock.create',
        'inventory.stock.update', 'inventory.stock.delete', 'inventory.stock.approve',
        'inventory.stock.export', 'inventory.stock.archive',
        'inventory.stock-movement.approve', 'inventory.stock-movement.export', 'inventory.stock-movement.archive',
        'inventory.warehouse.approve', 'inventory.warehouse.export', 'inventory.warehouse.archive',
    ];

    private const CORE_EXTRA_PERMISSIONS = [
        'core.customfield.view-any', 'core.customfield.view', 'core.customfield.create',
        'core.customfield.update', 'core.customfield.delete', 'core.customfield.approve',
        'core.customfield.export', 'core.customfield.archive',
    ];

    // Chantier 8.6 (Strategy): StrategyKpiPolicy checks strategy.kpi.{create,
    // update,delete} (StrategyKpiPolicy::create/update/delete) -- 'kpi' isn't
    // in MODULES['strategy'] at all (only ratio/objective/plan), so it was
    // never seeded and every non-admin/non-finance-manager KPI mutation
    // silently failed. RatioPolicy's strategy.ratio.* and
    // StrategyObjectivePolicy's strategy.objective.* are already covered by
    // MODULES['strategy'] => ['ratio', 'objective', 'plan'] below, so only
    // 'kpi' needs an extra block here, same reasoning as
    // LOGISTICS_EXTRA_PERMISSIONS' single missing 'deliveryround' resource.
    private const STRATEGY_EXTRA_PERMISSIONS = [
        'strategy.kpi.view-any', 'strategy.kpi.view', 'strategy.kpi.create',
        'strategy.kpi.update', 'strategy.kpi.delete',
    ];

    // Chantier 8 (Achats): PurchaseOrderPolicy::approve/reject were a
    // hollowed-out no-op ("return true" for every ability, with a code
    // comment admitting it was only that way for tests) -- gating a
    // financial approval step on "any authenticated user" including
    // warehouse-operator, which is the wrong business rule. 'approve'/
    // 'reject' aren't in the generic ACTIONS list (view-any/view/create/
    // update/delete only), so achats.purchase-order.{approve,reject} were
    // never seeded anywhere -- same reasoning as LOGISTICS_EXTRA_PERMISSIONS'
    // missing 'deliveryround' resource. purchasing-manager already gets
    // these via its 'achats.' wildcard match below; manager/admin get them
    // via $allPermissions; warehouse-operator (explicit in_array permission
    // list, no achats.* at all) correctly does not.
    private const ACHATS_EXTRA_PERMISSIONS = [
        'achats.purchase-order.approve', 'achats.purchase-order.reject',
    ];

    private const MODULES = [
        'crm'              => ['contact', 'lead', 'opportunity', 'account', 'activity', 'pipeline'],
        'security'         => ['incident', 'audit', 'compliance', 'encryption', 'threat', 'identity', 'zone'],
        'sales'            => ['order', 'line', 'quotation'],
        'hr'               => ['employee', 'department', 'job-position', 'leave', 'leave-type'],
        'payroll'          => ['payslip', 'run', 'tax-config'],
        'timesheets'       => ['timesheet', 'entry'],
        'projects'         => ['project', 'task'],
        // Chantier 17: 'product-template' (clothing-domain catalogue) and
        // 'sourcing-benchmark' (external price observations) added.
        // Chantier 21: 'costing-sheet' (fiche de chiffrage / BOM devis) added.
        // Chantier 23 (volet C): 'production-order' (commande de production simplifiée) added.
        'inventory'        => ['product', 'category', 'warehouse', 'unit', 'stock-movement', 'purchase-order', 'supplier', 'product-template', 'sourcing-benchmark', 'costing-sheet', 'production-order'],
        'logistics'        => ['shipment', 'route', 'carrier', 'customs-declaration'],
        'achats'           => ['rfq', 'purchase-order', 'purchase-receipt', 'supplier', 'purchaseorderline'],
        // Chantier 26 (volet D): 'financereview' (revue finance mensuelle/
        // trimestrielle — objectifs commerciaux + budget) added.
        'accounting'       => ['invoice', 'journal', 'chart-of-account', 'bank-account', 'expense', 'tax_compliance', 'consolidation', 'depreciation', 'intercompany', 'asset_impairment', 'depreciation_policy', 'budget', 'budget_scenario', 'financial-simulation', 'financereview'],
        'helpdesk'         => ['ticket', 'team', 'agent-performance'],
        'bi'               => ['dashboard', 'kpi', 'report', 'bidatasource'],
        'analytics'        => ['forecast', 'anomaly'],
        'reporting'        => ['report', 'template', 'schedule'],
        'strategy'         => ['ratio', 'objective', 'plan'],
        'auditlog'         => ['logs'],
        'setup'            => ['import', 'mapping', 'wizard'],
        // Chantier 32.6: 'external-integration' (IntegrationManager's
        // mobile-money/e-commerce/business-tools registry — Orange Money,
        // Wave, MTN MoMo, M-Pesa, Shopify, WooCommerce, Jumia, Google
        // Workspace, Zapier) added — a real, fully-written subsystem that
        // had zero controller/route/permission of any kind before this
        // chantier gave it its first-ever producer.
        'integration'      => ['connector', 'webhook', 'sync-log', 'external-integration'],
        // Chantier 32.9: 'group' removed — Modules\Settings\Models\SettingGroup
        // (and its `setting_groups` table) was deleted this chantier as
        // confirmed dead code (zero real consumers anywhere, and
        // independently broken as designed — see that migration's own
        // docblock). The `settings.group.*` strings this entry used to
        // generate were already dead weight even before the deletion —
        // SettingPolicy has only ever checked the flat settings.{view,
        // create,update,delete} strings from SETTINGS_PERMISSIONS below,
        // never settings.setting.*/settings.group.* (see that const's own
        // comment) — so this is pure hygiene, not a behavior change.
        'settings'         => ['setting'],
        'validation'       => ['workflow', 'rule', 'hierarchy', 'request'],
        // Chantier 20: internal team messaging — every seeded role picks this
        // up automatically via the generic loop below (employee gets it minus
        // .delete, matching this app's established "basic communication tool,
        // broad role" convention already used for Helpdesk's QuickTicketButton).
        'messaging'        => ['conversation'],
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

        foreach (self::CRM_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::SETTINGS_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::HR_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::HELPDESK_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::BI_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::CORE_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::LOGISTICS_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::SALES_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::SECURITY_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::PAYROLL_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::AUDITLOG_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::STRATEGY_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ACHATS_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::INVENTORY_EXTRA_PERMISSIONS as $name) {
            $allPermissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ── Roles ──────────────────────────────────────────────────────────────

        // super-admin: Gate::before bypass — no permission assignment needed
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // admin: every permission
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($allPermissions);

        // manager: all actions except delete on sensitive resources
        //
        // Chantier 32.3 (Security deep audit): 'security' is in MODULES like
        // every other module, so this generic loop was silently handing
        // manager (and employee, below) full security.* — including
        // security.encryption.rotate, security.identity.rotate (mint/rotate
        // service-to-service credentials), security.incident.*,
        // security.compliance.* — every day-to-day manager in the app, not
        // just security-admin/admin. The module's own routes/api.php already
        // scopes 2 of its 8 sub-resources (auth-events, rate-limits) to
        // role:security-admin,admin,super-admin, confirming the intended
        // audience — manager/employee were never meant to reach any of this,
        // it was purely a side effect of the generic $allPermissions loop.
        // Excluded here (defense-in-depth alongside the route-level role
        // gate added in the same chantier) rather than pulling 'security' out
        // of MODULES entirely, so admin/security-admin keep getting it via
        // their own existing wildcard/full-set grants below, unaffected.
        $managerPerms = array_filter($allPermissions, function (Permission $p) {
            // No delete on invoices/employees from managers
            if (str_ends_with($p->name, '.delete') &&
                (str_starts_with($p->name, 'accounting.') || str_starts_with($p->name, 'hr.'))) {
                return false;
            }
            if (str_starts_with($p->name, 'security.')) {
                return false;
            }
            return true;
        });
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions(array_values($managerPerms));

        // employee: view-any + view + create + update own (no delete)
        // Chantier 32.3: same security.* exclusion as manager above.
        $employeePerms = array_filter(
            $allPermissions,
            fn(Permission $p) => ! str_ends_with($p->name, '.delete') && ! str_starts_with($p->name, 'security.')
        );
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
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
                // Chantier 17 — useful when creating a product from a
                // template while receiving stock; benchmark pricing is a
                // purchasing/analyst concern, not extended here.
                'inventory.product-template.view-any', 'inventory.product-template.view',
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

        // finance-manager: full Accounting + full BI + Strategy, plus a narrow
        // read-only grant into Sales (Chantier 26 volet D — la revue finance
        // mensuelle/trimestrielle compare les objectifs commerciaux validés
        // aux ventes réelles, donc a besoin de lire Modules\Sales\Models\
        // SalesObjective/SalesOrder sans porter les permissions d'écriture
        // commerciale complètes — même précédent que la grille explicite déjà
        // utilisée ci-dessous pour customer-service).
        $financeManager = Role::firstOrCreate(['name' => 'finance-manager', 'guard_name' => 'web']);
        $financeManager->syncPermissions(array_filter($allPermissions, fn(Permission $p) =>
            str_starts_with($p->name, 'accounting.') ||
            str_starts_with($p->name, 'bi.') ||
            str_starts_with($p->name, 'strategy.') ||
            $p->name === 'sales.read'
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
