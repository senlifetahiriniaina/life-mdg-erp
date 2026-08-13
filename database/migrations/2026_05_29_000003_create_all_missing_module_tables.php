<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── ACCOUNTING (remaining) ──────────────────────────────────────────
        $acc = ['acc_budget_actuals','acc_budget_alerts','acc_budget_forecasts',
            'acc_consolidated_financial_statements','acc_consolidation_adjustments',
            'acc_consolidation_entities','acc_consolidation_groups','acc_consolidation_rules',
            'acc_consolidation_worksheets','acc_consolidations','acc_ebitda_reconciliations',
            'acc_entity_relationships','acc_expense_approvals','acc_expense_categories',
            'acc_expense_receipts','acc_financial_metric_trends','acc_financial_reports',
            'acc_fiscal_years','acc_generated_reports','acc_impairment_tests',
            'acc_intercompany_rules','acc_lease_payments','acc_minority_interests',
            'acc_ml_matching_metrics','acc_operating_leases','acc_outstanding_items',
            'acc_reconciliation_exceptions','acc_reconciliation_matches',
            'acc_reporting_currencies','acc_revenue_contracts','acc_revenue_recognition_events',
            'acc_revenue_recognition_policies','acc_segment_reports','acc_tax_automation_rules',
            'acc_tax_calculation_audits','acc_tax_categories','acc_tax_compliance',
            'acc_tax_deductions','acc_tax_rule_audit_logs','acc_tax_rules',
            'acc_trend_forecasts','acc_xbrl_exports'];
        foreach ($acc as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        // ── ACHATS ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('achats_suppliers')) {
            Schema::create('achats_suppliers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('country')->nullable();
                $table->string('currency', 10)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('achats_rfqs')) {
            Schema::create('achats_rfqs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('reference')->nullable();
                $table->string('status')->default('draft');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->date('deadline')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('achats_rfq_lines')) {
            Schema::create('achats_rfq_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rfq_id');
                $table->string('description')->nullable();
                $table->decimal('quantity', 12, 3)->default(0);
                $table->decimal('unit_price', 15, 2)->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('achats_supplier_quotes')) {
            Schema::create('achats_supplier_quotes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rfq_id');
                $table->unsignedBigInteger('supplier_id');
                $table->decimal('total_amount', 15, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('achats_purchase_orders')) {
            Schema::create('achats_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('reference')->nullable();
                $table->string('status')->default('draft');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->date('expected_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('achats_purchase_order_lines')) {
            Schema::create('achats_purchase_order_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id');
                $table->string('description')->nullable();
                $table->decimal('quantity', 12, 3)->default(0);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('total_price', 15, 2)->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('achats_purchase_receipts')) {
            Schema::create('achats_purchase_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id');
                $table->string('reference')->nullable();
                $table->date('received_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('achats_purchase_receipt_lines')) {
            Schema::create('achats_purchase_receipt_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('receipt_id');
                $table->unsignedBigInteger('order_line_id')->nullable();
                $table->decimal('quantity_received', 12, 3)->default(0);
                $table->timestamps();
            });
        }
        foreach (['achats_po_accounting_mappings','achats_po_budget_allocations','achats_po_inventory_mappings'] as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('purchase_order_id')->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── BI ─────────────────────────────────────────────────────────────
        $bi = ['bi_alert_events','bi_alerts','bi_anomalies','bi_dashboards','bi_data_sources',
            'bi_forecasts','bi_kpi_alerts','bi_kpi_history','bi_predictive_models',
            'bi_queries','bi_reports','bi_scheduled_reports','bi_widgets'];
        foreach ($bi as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('name')->nullable();
                    $table->text('config')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        // ── CORE ───────────────────────────────────────────────────────────
        $core = ['core_approval_decisions','core_approval_instances','core_approval_workflows',
            'core_custom_field_values','core_custom_fields','core_encrypted_fields',
            'core_import_jobs','core_import_rows','core_key_rotations',
            'core_tenant_exchange_history','core_tenant_exchanges','core_validation_audits',
            'core_workflow_definitions','core_workflow_states'];
        foreach ($core as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── CRM (remaining) ────────────────────────────────────────────────
        if (!Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('company')->nullable();
                $table->string('status')->default('new');
                $table->string('source')->nullable();
                $table->integer('score')->default(0);
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('crm_quotes')) {
            Schema::create('crm_quotes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('reference')->nullable();
                $table->unsignedBigInteger('opportunity_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->string('status')->default('draft');
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->date('valid_until')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('crm_quote_lines')) {
            Schema::create('crm_quote_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('quote_id');
                $table->string('description')->nullable();
                $table->decimal('quantity', 12, 3)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('total_price', 15, 2)->default(0);
                $table->timestamps();
            });
        }
        $crm = ['crm_ai_agent_runs','crm_ai_agents','crm_call_logs',
            'crm_email_sequence_enrollments','crm_email_sequence_steps','crm_email_sequences',
            'crm_engagement_signals','crm_forecasts','crm_opportunity_history',
            'crm_opportunity_scores','crm_pipeline_snapshots','crm_product_bundles',
            'crm_scoring_rules','crm_sequence_enrollments','crm_sequence_steps',
            'crm_web_form_submissions','crm_web_forms','crm_win_loss_records'];
        foreach ($crm as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── DOCUMENTS ─────────────────────────────────────────────────────
        $docs = ['doc_approval_decisions','doc_approval_instances','doc_approval_workflows',
            'doc_catalog_generations','doc_catalog_templates','doc_content_index',
            'doc_generated_documents','doc_signatories','doc_signature_requests',
            'doc_signature_signers','doc_signatures','doc_templates',
            'document_shares','document_versions','document_workspace_members','document_workspaces'];
        foreach ($docs as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('name')->nullable();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        // ── ECOMMERCE (remaining) ──────────────────────────────────────────
        if (!Schema::hasTable('ec_addresses')) {
            Schema::create('ec_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('type')->default('shipping');
                $table->string('name')->nullable();
                $table->string('address_line_1')->nullable();
                $table->string('city')->nullable();
                $table->string('country', 3)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('ec_carts')) {
            Schema::create('ec_carts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('session_id')->nullable();
                $table->string('currency', 10)->default('XOF');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('ec_cart_items')) {
            Schema::create('ec_cart_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cart_id')->index();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('ec_reviews')) {
            Schema::create('ec_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->tinyInteger('rating')->default(5);
                $table->text('comment')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('ec_shipping_zones')) {
            Schema::create('ec_shipping_zones', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('countries')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('ec_shipping_rates')) {
            Schema::create('ec_shipping_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->string('name')->nullable();
                $table->decimal('rate', 15, 2)->default(0);
                $table->timestamps();
            });
        }
        $ecom = ['ecom_promotion_uses','ecom_promotions','ecom_returns',
            'ecom_shipment_items','ecom_shipments','ecommerce_billing_cycles',
            'ecommerce_checkout_sessions','ecommerce_commissions','ecommerce_configurator_option_values',
            'ecommerce_configurator_options','ecommerce_configurator_rules','ecommerce_coupon_usages',
            'ecommerce_customers','ecommerce_product_configurators','ecommerce_recurring_charges',
            'ecommerce_rfq_communications','ecommerce_rfq_items','ecommerce_rfqs',
            'ecommerce_rma_communications','ecommerce_rma_items','ecommerce_rmas',
            'ecommerce_shipments','ecommerce_shipping_methods','ecommerce_subscription_plans',
            'ecommerce_subscriptions','ecommerce_themes','ecommerce_vendor_payouts',
            'ecommerce_vendor_portal','ecommerce_vendor_portal_products','ecommerce_vendor_products',
            'ecommerce_vendor_reviews','ecommerce_vendors'];
        foreach ($ecom as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        // ── EMAIL ─────────────────────────────────────────────────────────
        $email = ['email_automation_flows','email_automations','email_domain_authentications',
            'email_flow_enrollments','email_flow_steps','email_journey_steps','email_journeys',
            'email_segment_contacts','email_segments','email_smtp_configs',
            'email_template_blocks','email_templates','emails'];
        foreach ($email as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('name')->nullable();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── HELPDESK ──────────────────────────────────────────────────────
        $hd = ['hd_chat_messages','hd_chat_sessions','hd_escalation_events','hd_escalation_rules',
            'hd_helpdesk_sla_policies','hd_kb_article_views','hd_kb_portal_articles',
            'hd_kb_portal_categories','hd_sla_breaches','hd_sla_policies','hd_teams',
            'hd_ticket_comments','helpdesk_bot_deflections','helpdesk_csat_campaigns',
            'helpdesk_csat_surveys','helpdesk_forum_posts']; // 'helpdesk_forum_replies' owned by Helpdesk module
        foreach ($hd as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── HR (remaining) ────────────────────────────────────────────────
        $hr = ['hr_appraisal_competencies','hr_appraisal_cycles','hr_appraisal_goals',
            'hr_appraisals','hr_attendance','hr_attendance_records','hr_candidates',
            'hr_course_enrollments','hr_courses','hr_critical_positions',
            'hr_employee_skills','hr_interview_schedules','hr_interviews',
            'hr_job_applications','hr_job_postings','hr_learning_paths',
            'hr_performance_appraisals','hr_performance_cycles','hr_performance_goals',
            'hr_performance_reviews','hr_positions','hr_recruitment_applicants',
            'hr_recruitment_jobs','hr_review_cycles','hr_review_goals',
            'hr_salary_bands','hr_skills','hr_succession_candidates',
            'hr_succession_plans','hr_training_courses','hr_training_enrollments'];
        foreach ($hr as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        // ── INVENTORY (remaining) ─────────────────────────────────────────
        $inv = ['inventory_carriers','inventory_cost_layers','inventory_crossdock_operations',
            'inventory_cycle_count_lines','inventory_cycle_counts','inventory_demand_forecasts',
            'inventory_lot_movements','inventory_lots','inventory_pick_lines',
            'inventory_picking_lines','inventory_picking_orders','inventory_picking_waves',
            'inventory_po_receipt_lines','inventory_po_receipts','inventory_purchase_order_items',
            'inventory_purchase_orders','inventory_redistribution_rules','inventory_reorder_rules',
            'inventory_rmas','inventory_seasonal_factors','inventory_shipment_events',
            'inventory_shipments','inventory_suppliers','inventory_transfer_order_lines',
            'inventory_transfer_orders','inventory_valuation_runs'];
        foreach ($inv as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        // ── LOGISTICS ─────────────────────────────────────────────────────
        $log = ['logistics_carrier_rates','logistics_carriers','logistics_customs_declarations',
            'logistics_delivery_rounds','logistics_delivery_stops','logistics_freight_invoices',
            'logistics_locations','logistics_putaway_rules','logistics_routes',
            'logistics_shipment_lines','logistics_shipment_packages',
            'logistics_tracking_events'];
        foreach ($log as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }
        // logistics_shipments already in Shipment model table (logistics_shipments)
        if (!Schema::hasTable('logistics_shipments')) {
            Schema::create('logistics_shipments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('reference')->nullable();
                $table->string('status')->default('draft');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── MANUFACTURING ─────────────────────────────────────────────────
        $mfg = ['mfg_bom_components','mfg_bom_lines','mfg_boms','mfg_bottleneck_reports',
            'mfg_capacity_allocations','mfg_capacity_constraints','mfg_iot_devices',
            'mfg_iot_readings','mfg_lot_numbers','mfg_material_consumptions',
            'mfg_mrp_runs','mfg_mrp_suggestions','mfg_operation_resources','mfg_operations',
            'mfg_outsourced_orders','mfg_production_cost_records','mfg_production_orders',
            'mfg_quality_checks','mfg_routing_operations','mfg_routings','mfg_serial_numbers',
            'mfg_standard_costs','mfg_subcontractors','mfg_subcontracts',
            'mfg_traceability_events','mfg_work_order_materials','mfg_work_orders','mfg_workcenters'];
        foreach ($mfg as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        // ── MARKETING / CAMPAIGNS ─────────────────────────────────────────
        // 'campaigns' and 'marketing_contacts' are now created by the MarketingAutomation module.
        $mkt = ['campaign_executions','segments',
            'lead_activities','contact_consent_logs','contact_scores'];
        foreach ($mkt as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── MESSAGING / DISCUSSION ────────────────────────────────────────
        // These tables are now created by the Discussion module (canonical schema).
        $msg = [];
        foreach ($msg as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── PLANNING ─────────────────────────────────────────────────────
        $plan = ['planning_employee_schedules','planning_schedule_conflicts',
            'planning_schedule_templates','planning_shift_coverage_requests',
            'planning_shift_swap_requests','planning_shifts'];
        foreach ($plan as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── POS ──────────────────────────────────────────────────────────
        $pos = ['pos_cash_movements','pos_location_stock','pos_loyalty_accounts',
            'pos_loyalty_programs','pos_loyalty_rewards','pos_loyalty_tiers',
            'pos_loyalty_transactions','pos_order_items','pos_order_payments','pos_orders',
            'pos_payment_terminals','pos_registers','pos_return_lines','pos_returns',
            'pos_shifts','pos_stores','pos_table_reservations','pos_table_sections',
            'pos_tables','pos_terminal_transactions'];
        foreach ($pos as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        // ── PROJECTS ─────────────────────────────────────────────────────
        $prj = ['prj_automation_rules','prj_epics','prj_project_billing',
            'prj_resource_allocations','prj_resource_capacity','prj_sprints',
            'prj_team_members','prj_time_entries','project_task_dependencies'];
        foreach ($prj as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── QUALITY ──────────────────────────────────────────────────────
        $quality = ['quality_defects','quality_history','quality_inspections',
            'quality_issues','quality_non_conformances','inspection_checklists',
            'production_defects','corrective_actions','supplier_quality_metrics'];
        foreach ($quality as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── SECURITY / DDOS ───────────────────────────────────────────────
        if (!Schema::hasTable('ddos_incidents')) {
            Schema::create('ddos_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('ip_address', 45)->index();
                $table->string('type')->nullable();
                $table->string('severity')->nullable();
                $table->text('metadata')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // ── TIMESHEETS ────────────────────────────────────────────────────
        foreach (['timesheet_entries','timesheets_entries','timesheets_sheets','time_allocations',
            'time_tracking_projects'] as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── VALIDATION ────────────────────────────────────────────────────
        $val = ['validation_approval_actions','validation_approval_hierarchies',
            'validation_approval_history','validation_approval_requests',
            'validation_approval_rules','validation_approval_workflows',
            'validation_hierarchy_levels','validation_level_approvers'];
        foreach ($val as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── WHATSAPP ─────────────────────────────────────────────────────
        $wa = ['wa_agent_stats_daily','wa_broadcast_campaigns','wa_campaign_recipients',
            'wa_catalog_products','wa_chatbot_intents','wa_chatbot_messages',
            'wa_chatbot_sessions','wa_interactive_messages_sent','wa_interactive_templates',
            'wa_templates','whatsapp_broadcasts','whatsapp_optins',
            'whatsapp_payment_configs','whatsapp_payments'];
        foreach ($wa as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── WORKFLOW ─────────────────────────────────────────────────────
        $wf = ['wfd_actions','wfd_definitions','wfd_execution_logs','wfd_executions',
            'webhook_events','workflow_actions','workflow_chain_definitions',
            'workflow_chain_executions','workflow_conditions','workflow_execution_steps',
            'workflow_steps','workflow_triggers'];
        foreach ($wf as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->string('status')->nullable();
                    $table->text('data')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── GENERIC / SHARED ─────────────────────────────────────────────
        if (!Schema::hasTable('bill_of_materials')) {
            Schema::create('bill_of_materials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name')->nullable();
                $table->string('status')->default('draft');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('bom_lines')) {
            Schema::create('bom_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bom_id')->index();
                $table->string('component')->nullable();
                $table->decimal('quantity', 12, 3)->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('reference')->nullable();
                $table->date('received_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('sku')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        $gen = ['product_changes','product_specifications','product_variants',
            'product_versions','product_webhooks','purchase_order_lines',
            'purchase_orders','suppliers'];
        foreach ($gen as $t) {
            if (!Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->text('data')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty — destructive rollback not supported
    }
};
