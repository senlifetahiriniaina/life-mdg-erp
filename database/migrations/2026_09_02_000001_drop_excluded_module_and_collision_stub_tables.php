<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops dead-weight tables confirmed to have zero live code references anywhere
 * in the repo (grep across Modules/, app/, database/, tests/, routes/, resources/js/,
 * config/ — excluding migrations themselves and already-dead factories).
 *
 * Three groups:
 *
 * 1. Tables scaffolded by the catch-all `2026_05_29_000003_create_all_missing_module_tables.php`
 *    (and a handful of real, non-stub tables created by the dedicated
 *    `2026_05_01_000004/000005/000006_*` migrations) for modules that were explicitly
 *    EXCLUDED from Life MDG's 27-module scope during extraction from WideHalo-ERP:
 *    Documents, Ecommerce, Email, Manufacturing, Planning, POS, Quality, WhatsApp,
 *    MarketingAutomation. These modules have zero `Modules/<Name>/` directory on disk.
 *
 * 2. Generic-name stub tables created by the same catch-all migration with no owning
 *    module and no Eloquent model anywhere in the repo (`suppliers`, `purchase_orders`,
 *    `product_changes`, `product_specifications`, `product_variants`, `product_versions`,
 *    `product_webhooks`). NOTE: `products`, `customers`, and `purchase_order_lines` were
 *    also candidates but are NOT included here — each has a real, live reader
 *    (see the accompanying chantier note in CLAUDE.md for the full list of what was
 *    investigated and kept).
 *
 * 3. The dead root-namespace `App\Models\{Workflow,Approval,ApprovalChain,WorkflowAuditLog,
 *    WorkflowStepLog}` subsystem's tables (`App\Services\WorkflowEngine` and its models were
 *    confirmed to reference only each other — zero controller/route/test/factory/seeder
 *    reference anywhere). `workflow_executions` and `approval_overrides` are deliberately
 *    NOT dropped — see the CLAUDE.md chantier note for why.
 *
 * This is a one-way cleanup: down() intentionally does not attempt to recreate the
 * generic id/tenant_id/data/timestamps stub schema (or the handful of real ecommerce/
 * documents/pos/manufacturing/email/whatsapp schemas), matching how prior "stub table"
 * cleanups in this app have never bothered preserving dead scaffold schemas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Real, non-stub tables with FK relationships — drop children before parents ──
        $orderedRealTables = [
            // Ecommerce (2026_05_01_000004): ec_order_items/ec_payments -> ec_orders/ec_products;
            // ec_products/ec_orders/ec_categories/ec_coupons -> ec_stores
            'ec_order_items',
            'ec_payments',
            'ec_products',
            'ec_orders',
            'ec_categories',
            'ec_coupons',
            'ec_stores',
            // Documents (2026_05_01_000006): doc_folders is self-referential only, independent
            // of the (kept) `documents` table
            'doc_folders',
            // POS: pos_sessions.config_id -> pos_configs
            'pos_sessions',
            'pos_configs',
            // Manufacturing: mfg_bom_items.bom_id -> mfg_bill_of_materials
            'mfg_bom_items',
            'mfg_bill_of_materials',
            // Email: email_campaigns/email_subscribers.list_id -> email_lists
            'email_campaigns',
            'email_subscribers',
            'email_lists',
            // WhatsApp: wa_messages/wa_conversations.contact_id -> wa_contacts
            'wa_messages',
            'wa_conversations',
            'wa_contacts',
        ];

        foreach ($orderedRealTables as $t) {
            Schema::dropIfExists($t);
        }

        // ── Documents (catch-all stub block) — doc_approval_instances kept: live in
        //    app/Services/DashboardService.php (root dashboard pending-approvals widget) ──
        $documents = [
            'doc_approval_decisions', 'doc_approval_workflows', 'doc_catalog_generations',
            'doc_catalog_templates', 'doc_content_index', 'doc_generated_documents',
            'doc_signatories', 'doc_signature_requests', 'doc_signature_signers',
            'doc_signatures', 'doc_templates', 'document_shares', 'document_versions',
            'document_workspace_members', 'document_workspaces',
        ];

        // ── Ecommerce (catch-all stub block) ──
        $ecommerce = [
            'ec_addresses', 'ec_carts', 'ec_cart_items', 'ec_reviews',
            'ec_shipping_zones', 'ec_shipping_rates',
            'ecom_promotion_uses', 'ecom_promotions', 'ecom_returns',
            'ecom_shipment_items', 'ecom_shipments',
            'ecommerce_billing_cycles', 'ecommerce_checkout_sessions', 'ecommerce_commissions',
            'ecommerce_configurator_option_values', 'ecommerce_configurator_options',
            'ecommerce_configurator_rules', 'ecommerce_coupon_usages', 'ecommerce_customers',
            'ecommerce_product_configurators', 'ecommerce_recurring_charges',
            'ecommerce_rfq_communications', 'ecommerce_rfq_items', 'ecommerce_rfqs',
            'ecommerce_rma_communications', 'ecommerce_rma_items', 'ecommerce_rmas',
            'ecommerce_shipments', 'ecommerce_shipping_methods', 'ecommerce_subscription_plans',
            'ecommerce_subscriptions', 'ecommerce_themes', 'ecommerce_vendor_payouts',
            'ecommerce_vendor_portal', 'ecommerce_vendor_portal_products',
            'ecommerce_vendor_products', 'ecommerce_vendor_reviews', 'ecommerce_vendors',
        ];

        // ── Email (catch-all stub block) ──
        $email = [
            'email_automation_flows', 'email_automations', 'email_domain_authentications',
            'email_flow_enrollments', 'email_flow_steps', 'email_journey_steps',
            'email_journeys', 'email_segment_contacts', 'email_segments',
            'email_smtp_configs', 'email_template_blocks', 'email_templates', 'emails',
        ];

        // ── Manufacturing (catch-all stub block) — mfg_production_orders kept: live in
        //    Modules/Strategy/app/Services/KPIRegistryService.php (TRS/defect-rate ratios) ──
        $manufacturing = [
            'mfg_bom_components', 'mfg_bom_lines', 'mfg_boms', 'mfg_bottleneck_reports',
            'mfg_capacity_allocations', 'mfg_capacity_constraints', 'mfg_iot_devices',
            'mfg_iot_readings', 'mfg_lot_numbers', 'mfg_material_consumptions',
            'mfg_mrp_runs', 'mfg_mrp_suggestions', 'mfg_operation_resources', 'mfg_operations',
            'mfg_outsourced_orders', 'mfg_production_cost_records', 'mfg_quality_checks',
            'mfg_routing_operations', 'mfg_routings', 'mfg_serial_numbers', 'mfg_standard_costs',
            'mfg_subcontractors', 'mfg_subcontracts', 'mfg_traceability_events',
            'mfg_work_order_materials', 'mfg_work_orders', 'mfg_workcenters',
        ];

        // ── Planning (catch-all stub block) ──
        $planning = [
            'planning_employee_schedules', 'planning_schedule_conflicts',
            'planning_schedule_templates', 'planning_shift_coverage_requests',
            'planning_shift_swap_requests', 'planning_shifts',
        ];

        // ── POS (catch-all stub block) ──
        $pos = [
            'pos_cash_movements', 'pos_location_stock', 'pos_loyalty_accounts',
            'pos_loyalty_programs', 'pos_loyalty_rewards', 'pos_loyalty_tiers',
            'pos_loyalty_transactions', 'pos_order_items', 'pos_order_payments', 'pos_orders',
            'pos_payment_terminals', 'pos_registers', 'pos_return_lines', 'pos_returns',
            'pos_shifts', 'pos_stores', 'pos_table_reservations', 'pos_table_sections',
            'pos_tables', 'pos_terminal_transactions',
        ];

        // ── Quality (catch-all stub block) — quality_inspections kept: live in
        //    Modules/Workflow/app/Services/Actions/QualityActionHandler.php (wired workflow action) ──
        $quality = [
            'quality_defects', 'quality_history', 'quality_issues', 'quality_non_conformances',
            'inspection_checklists', 'production_defects', 'corrective_actions',
            'supplier_quality_metrics',
        ];

        // ── WhatsApp (catch-all stub block) ──
        $whatsapp = [
            'wa_agent_stats_daily', 'wa_broadcast_campaigns', 'wa_campaign_recipients',
            'wa_catalog_products', 'wa_chatbot_intents', 'wa_chatbot_messages',
            'wa_chatbot_sessions', 'wa_interactive_messages_sent', 'wa_interactive_templates',
            'wa_templates', 'whatsapp_broadcasts', 'whatsapp_optins',
            'whatsapp_payment_configs', 'whatsapp_payments',
        ];

        // ── MarketingAutomation (catch-all stub block, "MARKETING / CAMPAIGNS" section) ──
        $marketing = [
            'campaign_executions', 'segments', 'lead_activities',
            'contact_consent_logs', 'contact_scores',
        ];

        // ── Generic-name collision stub tables (no owning module, no Eloquent model) ──
        $generic = [
            'suppliers', 'purchase_orders', 'product_changes', 'product_specifications',
            'product_variants', 'product_versions', 'product_webhooks',
        ];

        foreach ([
            ...$documents, ...$ecommerce, ...$email, ...$manufacturing, ...$planning,
            ...$pos, ...$quality, ...$whatsapp, ...$marketing, ...$generic,
        ] as $t) {
            Schema::dropIfExists($t);
        }

        // ── Dead root-namespace WorkflowEngine subsystem (app/Models + app/Services) ──
        // workflow_executions and approval_overrides are deliberately NOT dropped:
        // workflow_executions is read live by Modules/Calendar's ModuleEventAggregatorService
        // (importWorkflowSchedules(), wired into its source map); approval_overrides is
        // written live by app/Services/AuditLog/ApprovalOverrideService.php — a different,
        // out-of-scope service not named for deletion by this chantier.
        //
        // FK graph (from 2026_05_17_170000_create_workflow_automation_tables.php):
        //   workflow_executions.workflow_id -> workflows           (workflow_executions is KEPT)
        //   workflow_step_logs.execution_id -> workflow_executions (KEPT, but workflow_step_logs
        //                                                            itself is dropped — fine)
        //   approval_chains.step_log_id     -> workflow_step_logs
        //   workflow_conditions.step_log_id -> workflow_step_logs
        //   approvals.chain_id              -> approval_chains
        // Children must drop before parents; and since `workflows` is dropped while the KEPT
        // workflow_executions still references it, that one FK constraint must be dropped
        // explicitly first (table stays, only the constraint goes).
        foreach (['approvals', 'approval_chains', 'workflow_conditions', 'workflow_step_logs'] as $t) {
            Schema::dropIfExists($t);
        }

        if (Schema::hasTable('workflow_executions') && Schema::hasColumn('workflow_executions', 'workflow_id')) {
            Schema::table('workflow_executions', function ($table) {
                $table->dropForeign(['workflow_id']);
            });
        }

        foreach (['workflows', 'approval_requests', 'workflow_triggers', 'workflow_audit_logs'] as $t) {
            Schema::dropIfExists($t);
        }
    }

    public function down(): void
    {
        // Intentionally left empty — these are dead generic stub tables (and one confirmed-dead
        // real-schema subsystem) with zero live readers; there is no schema worth restoring.
        // Matches the down() convention already established by
        // 2026_05_29_000003_create_all_missing_module_tables.php and this session's other
        // destructive-cleanup migrations.
    }
};
