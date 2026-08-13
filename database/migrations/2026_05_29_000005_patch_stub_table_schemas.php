<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function col(string $table, string $column, \Closure $cb): void
    {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
            Schema::table($table, fn(Blueprint $t) => $cb($t));
        }
    }

    public function up(): void
    {
        // ── MANUFACTURING ──────────────────────────────────────────────────
        // mfg_bill_of_materials
        if (Schema::hasTable('mfg_bill_of_materials')) {
            Schema::table('mfg_bill_of_materials', function (Blueprint $t) {
                foreach (['product_id','unit_id','type','is_active','notes','quantity','version'] as $c) {
                    if (!Schema::hasColumn('mfg_bill_of_materials', $c)) {
                        match($c) {
                            'product_id','unit_id' => $t->unsignedBigInteger($c)->nullable(),
                            'type' => $t->string($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'notes' => $t->text($c)->nullable(),
                            'quantity' => $t->decimal($c, 12, 4)->default(1),
                            'version' => $t->integer($c)->default(1),
                        };
                    }
                }
            });
        }
        // mfg_workcenters
        if (Schema::hasTable('mfg_workcenters')) {
            Schema::table('mfg_workcenters', function (Blueprint $t) {
                foreach (['code','capacity','efficiency','hourly_cost','is_active','currency','department'] as $c) {
                    if (!Schema::hasColumn('mfg_workcenters', $c)) {
                        match($c) {
                            'code' => $t->string($c)->nullable(),
                            'capacity','efficiency','hourly_cost' => $t->decimal($c, 12, 4)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'currency' => $t->string($c, 10)->default('XOF'),
                            'department' => $t->string($c)->nullable(),
                        };
                    }
                }
            });
        }
        // mfg_production_orders
        if (Schema::hasTable('mfg_production_orders')) {
            Schema::table('mfg_production_orders', function (Blueprint $t) {
                foreach (['bom_id','product_id','workcenter_id','reference','quantity','quantity_produced',
                    'planned_start','planned_end','actual_start','actual_end','priority','created_by'] as $c) {
                    if (!Schema::hasColumn('mfg_production_orders', $c)) {
                        match($c) {
                            'bom_id','product_id','workcenter_id','created_by' => $t->unsignedBigInteger($c)->nullable(),
                            'reference' => $t->string($c)->nullable(),
                            'quantity','quantity_produced' => $t->decimal($c, 12, 4)->default(0),
                            'planned_start','planned_end','actual_start','actual_end' => $t->timestamp($c)->nullable(),
                            'priority' => $t->string($c)->nullable(),
                        };
                    }
                }
            });
        }
        // mfg_work_orders
        if (Schema::hasTable('mfg_work_orders')) {
            Schema::table('mfg_work_orders', function (Blueprint $t) {
                foreach (['production_order_id','workcenter_id','operation_id','reference','quantity',
                    'planned_start','planned_end','actual_start','actual_end','created_by'] as $c) {
                    if (!Schema::hasColumn('mfg_work_orders', $c)) {
                        match($c) {
                            'production_order_id','workcenter_id','operation_id','created_by' => $t->unsignedBigInteger($c)->nullable(),
                            'reference' => $t->string($c)->nullable(),
                            'quantity' => $t->decimal($c, 12, 4)->default(0),
                            'planned_start','planned_end','actual_start','actual_end' => $t->timestamp($c)->nullable(),
                        };
                    }
                }
            });
        }
        // mfg_routings
        if (Schema::hasTable('mfg_routings')) {
            Schema::table('mfg_routings', function (Blueprint $t) {
                foreach (['product_id','code','is_active'] as $c) {
                    if (!Schema::hasColumn('mfg_routings', $c)) {
                        match($c) {
                            'product_id' => $t->unsignedBigInteger($c)->nullable(),
                            'code' => $t->string($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        // mfg_quality_checks
        if (Schema::hasTable('mfg_quality_checks')) {
            Schema::table('mfg_quality_checks', function (Blueprint $t) {
                foreach (['production_order_id','inspector_id','result','passed','notes','checked_at'] as $c) {
                    if (!Schema::hasColumn('mfg_quality_checks', $c)) {
                        match($c) {
                            'production_order_id','inspector_id' => $t->unsignedBigInteger($c)->nullable(),
                            'result' => $t->string($c)->nullable(),
                            'passed' => $t->boolean($c)->nullable(),
                            'notes' => $t->text($c)->nullable(),
                            'checked_at' => $t->timestamp($c)->nullable(),
                        };
                    }
                }
            });
        }
        // mfg_bom_lines / mfg_bom_components
        foreach (['mfg_bom_lines','mfg_bom_components'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                    foreach (['bom_id','component_id','quantity','unit_id','is_optional'] as $c) {
                        if (!Schema::hasColumn($tbl, $c)) {
                            match($c) {
                                'bom_id','component_id','unit_id' => $t->unsignedBigInteger($c)->nullable(),
                                'quantity' => $t->decimal($c, 12, 4)->default(1),
                                'is_optional' => $t->boolean($c)->default(false),
                            };
                        }
                    }
                });
            }
        }
        // mfg_mrp_runs / mfg_mrp_suggestions
        if (Schema::hasTable('mfg_mrp_runs')) {
            Schema::table('mfg_mrp_runs', function (Blueprint $t) {
                foreach (['run_date','horizon_days','created_by','is_active'] as $c) {
                    if (!Schema::hasColumn('mfg_mrp_runs', $c)) {
                        match($c) {
                            'run_date' => $t->date($c)->nullable(),
                            'horizon_days' => $t->integer($c)->default(30),
                            'created_by' => $t->unsignedBigInteger($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('mfg_mrp_suggestions')) {
            Schema::table('mfg_mrp_suggestions', function (Blueprint $t) {
                foreach (['mrp_run_id','product_id','type','quantity','planned_date','priority'] as $c) {
                    if (!Schema::hasColumn('mfg_mrp_suggestions', $c)) {
                        match($c) {
                            'mrp_run_id','product_id' => $t->unsignedBigInteger($c)->nullable(),
                            'type','priority' => $t->string($c)->nullable(),
                            'quantity' => $t->decimal($c, 12, 4)->default(0),
                            'planned_date' => $t->date($c)->nullable(),
                        };
                    }
                }
            });
        }

        // ── INVENTORY (stub table patches) ────────────────────────────────
        if (Schema::hasTable('inventory_carriers')) {
            Schema::table('inventory_carriers', function (Blueprint $t) {
                foreach (['name','code','tracking_url_template','api_key','active','settings'] as $c) {
                    if (!Schema::hasColumn('inventory_carriers', $c)) {
                        match($c) {
                            'name','code' => $t->string($c)->nullable(),
                            'tracking_url_template' => $t->string($c, 512)->nullable(),
                            'api_key' => $t->string($c, 512)->nullable(),
                            'active' => $t->boolean($c)->default(true),
                            'settings' => $t->text($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('inventory_cycle_counts')) {
            Schema::table('inventory_cycle_counts', function (Blueprint $t) {
                foreach (['warehouse_id','reference','count_date','created_by','is_active'] as $c) {
                    if (!Schema::hasColumn('inventory_cycle_counts', $c)) {
                        match($c) {
                            'warehouse_id','created_by' => $t->unsignedBigInteger($c)->nullable(),
                            'reference' => $t->string($c)->nullable(),
                            'count_date' => $t->date($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('inventory_lots')) {
            Schema::table('inventory_lots', function (Blueprint $t) {
                foreach (['product_id','lot_number','expiry_date','quantity','location_id'] as $c) {
                    if (!Schema::hasColumn('inventory_lots', $c)) {
                        match($c) {
                            'product_id','location_id' => $t->unsignedBigInteger($c)->nullable(),
                            'lot_number' => $t->string($c)->nullable(),
                            'expiry_date' => $t->date($c)->nullable(),
                            'quantity' => $t->decimal($c, 12, 4)->default(0),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('inventory_transfer_orders')) {
            Schema::table('inventory_transfer_orders', function (Blueprint $t) {
                foreach (['reference','from_warehouse_id','to_warehouse_id','requested_by','approved_by'] as $c) {
                    if (!Schema::hasColumn('inventory_transfer_orders', $c)) {
                        match($c) {
                            'reference' => $t->string($c)->nullable(),
                            default => $t->unsignedBigInteger($c)->nullable(),
                        };
                    }
                }
            });
        }

        // ── HELPDESK (stub table patches) ─────────────────────────────────
        if (Schema::hasTable('hd_teams')) {
            Schema::table('hd_teams', function (Blueprint $t) {
                foreach (['name','description','lead_id','is_active'] as $c) {
                    if (!Schema::hasColumn('hd_teams', $c)) {
                        match($c) {
                            'name','description' => $t->string($c)->nullable(),
                            'lead_id' => $t->unsignedBigInteger($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('hd_sla_policies')) {
            Schema::table('hd_sla_policies', function (Blueprint $t) {
                foreach (['name','first_response_hours','resolution_hours','is_active','priority'] as $c) {
                    if (!Schema::hasColumn('hd_sla_policies', $c)) {
                        match($c) {
                            'name','priority' => $t->string($c)->nullable(),
                            'first_response_hours','resolution_hours' => $t->integer($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('hd_kb_portal_articles')) {
            Schema::table('hd_kb_portal_articles', function (Blueprint $t) {
                foreach (['title','content','category_id','author_id','is_published','views'] as $c) {
                    if (!Schema::hasColumn('hd_kb_portal_articles', $c)) {
                        match($c) {
                            'title' => $t->string($c)->nullable(),
                            'content' => $t->text($c)->nullable(),
                            'category_id','author_id' => $t->unsignedBigInteger($c)->nullable(),
                            'is_published' => $t->boolean($c)->default(false),
                            'views' => $t->integer($c)->default(0),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('hd_kb_portal_categories')) {
            Schema::table('hd_kb_portal_categories', function (Blueprint $t) {
                foreach (['name','description','parent_id','is_active','created_by'] as $c) {
                    if (!Schema::hasColumn('hd_kb_portal_categories', $c)) {
                        match($c) {
                            'name','description' => $t->string($c)->nullable(),
                            'parent_id','created_by' => $t->unsignedBigInteger($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('hd_escalation_rules')) {
            Schema::table('hd_escalation_rules', function (Blueprint $t) {
                foreach (['name','condition','action','threshold_hours','is_active','priority'] as $c) {
                    if (!Schema::hasColumn('hd_escalation_rules', $c)) {
                        match($c) {
                            'name','condition','action' => $t->string($c)->nullable(),
                            'threshold_hours' => $t->integer($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'priority' => $t->integer($c)->default(0),
                        };
                    }
                }
            });
        }

        // ── CORE (stub table patches) ──────────────────────────────────────
        if (Schema::hasTable('core_workflow_definitions')) {
            Schema::table('core_workflow_definitions', function (Blueprint $t) {
                foreach (['name','module','resource_type','is_active','steps','transitions'] as $c) {
                    if (!Schema::hasColumn('core_workflow_definitions', $c)) {
                        match($c) {
                            'name','module','resource_type' => $t->string($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'steps','transitions' => $t->text($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('core_import_jobs')) {
            Schema::table('core_import_jobs', function (Blueprint $t) {
                foreach (['user_id','filename','entity_type','total_rows','processed','failed','is_complete'] as $c) {
                    if (!Schema::hasColumn('core_import_jobs', $c)) {
                        match($c) {
                            'user_id' => $t->unsignedBigInteger($c)->nullable(),
                            'filename','entity_type' => $t->string($c)->nullable(),
                            'total_rows','processed','failed' => $t->integer($c)->default(0),
                            'is_complete' => $t->boolean($c)->default(false),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('core_approval_workflows')) {
            Schema::table('core_approval_workflows', function (Blueprint $t) {
                foreach (['name','module','resource_type','is_active','steps_count','requires_all'] as $c) {
                    if (!Schema::hasColumn('core_approval_workflows', $c)) {
                        match($c) {
                            'name','module','resource_type' => $t->string($c)->nullable(),
                            'is_active','requires_all' => $t->boolean($c)->default(true),
                            'steps_count' => $t->integer($c)->default(1),
                        };
                    }
                }
            });
        }

        // ── CRM (stub table patches) ───────────────────────────────────────
        if (Schema::hasTable('crm_email_sequences')) {
            Schema::table('crm_email_sequences', function (Blueprint $t) {
                foreach (['name','description','trigger_type','is_active','created_by'] as $c) {
                    if (!Schema::hasColumn('crm_email_sequences', $c)) {
                        match($c) {
                            'name','description','trigger_type' => $t->string($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'created_by' => $t->unsignedBigInteger($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('crm_forecasts')) {
            Schema::table('crm_forecasts', function (Blueprint $t) {
                foreach (['pipeline_id','user_id','period','amount','weighted_amount','ai_prediction'] as $c) {
                    if (!Schema::hasColumn('crm_forecasts', $c)) {
                        match($c) {
                            'pipeline_id','user_id' => $t->unsignedBigInteger($c)->nullable(),
                            'period' => $t->string($c)->nullable(),
                            'amount','weighted_amount','ai_prediction' => $t->decimal($c, 15, 4)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('crm_scoring_rules')) {
            Schema::table('crm_scoring_rules', function (Blueprint $t) {
                foreach (['name','entity_type','condition_field','condition_operator','condition_value','points'] as $c) {
                    if (!Schema::hasColumn('crm_scoring_rules', $c)) {
                        match($c) {
                            'name','entity_type','condition_field','condition_operator','condition_value' => $t->string($c)->nullable(),
                            'points' => $t->integer($c)->default(0),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('crm_web_forms')) {
            Schema::table('crm_web_forms', function (Blueprint $t) {
                foreach (['name','slug','fields','default_lead_source','is_active','created_by'] as $c) {
                    if (!Schema::hasColumn('crm_web_forms', $c)) {
                        match($c) {
                            'name','slug','default_lead_source' => $t->string($c)->nullable(),
                            'fields' => $t->text($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'created_by' => $t->unsignedBigInteger($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('crm_ai_agents')) {
            Schema::table('crm_ai_agents', function (Blueprint $t) {
                foreach (['name','trigger_type','action_type','config','is_active','created_by'] as $c) {
                    if (!Schema::hasColumn('crm_ai_agents', $c)) {
                        match($c) {
                            'name','trigger_type','action_type' => $t->string($c)->nullable(),
                            'config' => $t->text($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'created_by' => $t->unsignedBigInteger($c)->nullable(),
                        };
                    }
                }
            });
        }

        // ── LOGISTICS (stub table patches) ────────────────────────────────
        if (Schema::hasTable('logistics_carriers')) {
            Schema::table('logistics_carriers', function (Blueprint $t) {
                foreach (['name','code','type','is_active','contact_email','contact_phone'] as $c) {
                    if (!Schema::hasColumn('logistics_carriers', $c)) {
                        match($c) {
                            'name','code','type','contact_email','contact_phone' => $t->string($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('logistics_routes')) {
            Schema::table('logistics_routes', function (Blueprint $t) {
                foreach (['name','origin','destination','carrier_id','estimated_days','cost','is_active'] as $c) {
                    if (!Schema::hasColumn('logistics_routes', $c)) {
                        match($c) {
                            'name','origin','destination' => $t->string($c)->nullable(),
                            'carrier_id' => $t->unsignedBigInteger($c)->nullable(),
                            'estimated_days' => $t->integer($c)->nullable(),
                            'cost' => $t->decimal($c, 15, 4)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('logistics_customs_declarations')) {
            Schema::table('logistics_customs_declarations', function (Blueprint $t) {
                foreach (['shipment_id','declaration_number','country','declared_value','currency','status'] as $c) {
                    if (!Schema::hasColumn('logistics_customs_declarations', $c)) {
                        match($c) {
                            'shipment_id' => $t->unsignedBigInteger($c)->nullable(),
                            'declaration_number','country','currency' => $t->string($c)->nullable(),
                            'declared_value' => $t->decimal($c, 15, 4)->nullable(),
                            'status' => $t->string($c)->default('pending'),
                        };
                    }
                }
            });
        }

        // ── HR (remaining stubs) ──────────────────────────────────────────
        if (Schema::hasTable('hr_appraisals')) {
            Schema::table('hr_appraisals', function (Blueprint $t) {
                foreach (['employee_id','reviewer_id','cycle_id','period','rating','comments','submitted_at'] as $c) {
                    if (!Schema::hasColumn('hr_appraisals', $c)) {
                        match($c) {
                            'employee_id','reviewer_id','cycle_id' => $t->unsignedBigInteger($c)->nullable(),
                            'period','comments' => $t->string($c)->nullable(),
                            'rating' => $t->decimal($c, 5, 2)->nullable(),
                            'submitted_at' => $t->timestamp($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('hr_job_postings')) {
            Schema::table('hr_job_postings', function (Blueprint $t) {
                foreach (['title','department_id','location','type','salary_min','salary_max',
                    'currency','description','requirements','is_published','closes_at','created_by'] as $c) {
                    if (!Schema::hasColumn('hr_job_postings', $c)) {
                        match($c) {
                            'title','location','type','currency' => $t->string($c)->nullable(),
                            'department_id','created_by' => $t->unsignedBigInteger($c)->nullable(),
                            'salary_min','salary_max' => $t->decimal($c, 15, 4)->nullable(),
                            'description','requirements' => $t->text($c)->nullable(),
                            'is_published' => $t->boolean($c)->default(false),
                            'closes_at' => $t->date($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('hr_candidates')) {
            Schema::table('hr_candidates', function (Blueprint $t) {
                foreach (['first_name','last_name','email','phone','source','cv_path','rating'] as $c) {
                    if (!Schema::hasColumn('hr_candidates', $c)) {
                        match($c) {
                            'first_name','last_name','email','phone','source','cv_path' => $t->string($c)->nullable(),
                            'rating' => $t->tinyInteger($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('hr_training_courses')) {
            Schema::table('hr_training_courses', function (Blueprint $t) {
                foreach (['title','description','provider','duration_hours','cost','currency','is_mandatory'] as $c) {
                    if (!Schema::hasColumn('hr_training_courses', $c)) {
                        match($c) {
                            'title','provider','currency' => $t->string($c)->nullable(),
                            'description' => $t->text($c)->nullable(),
                            'duration_hours' => $t->integer($c)->nullable(),
                            'cost' => $t->decimal($c, 15, 4)->nullable(),
                            'is_mandatory' => $t->boolean($c)->default(false),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('hr_succession_plans')) {
            Schema::table('hr_succession_plans', function (Blueprint $t) {
                foreach (['position_id','incumbent_id','created_by','review_date','is_active'] as $c) {
                    if (!Schema::hasColumn('hr_succession_plans', $c)) {
                        match($c) {
                            'position_id','incumbent_id','created_by' => $t->unsignedBigInteger($c)->nullable(),
                            'review_date' => $t->date($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }

        // ── ECOMMERCE (stub table patches) ────────────────────────────────
        if (Schema::hasTable('ecommerce_vendors')) {
            Schema::table('ecommerce_vendors', function (Blueprint $t) {
                foreach (['user_id','company_name','email','phone','commission_rate','is_verified','is_active'] as $c) {
                    if (!Schema::hasColumn('ecommerce_vendors', $c)) {
                        match($c) {
                            'user_id' => $t->unsignedBigInteger($c)->nullable(),
                            'company_name','email','phone' => $t->string($c)->nullable(),
                            'commission_rate' => $t->decimal($c, 5, 2)->default(0),
                            'is_verified','is_active' => $t->boolean($c)->default(false),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('ecommerce_subscriptions')) {
            Schema::table('ecommerce_subscriptions', function (Blueprint $t) {
                foreach (['customer_id','plan_id','trial_ends_at','current_period_start','current_period_end',
                    'cancelled_at','amount','currency'] as $c) {
                    if (!Schema::hasColumn('ecommerce_subscriptions', $c)) {
                        match($c) {
                            'customer_id','plan_id' => $t->unsignedBigInteger($c)->nullable(),
                            'trial_ends_at','current_period_start','current_period_end','cancelled_at' => $t->timestamp($c)->nullable(),
                            'amount' => $t->decimal($c, 15, 4)->default(0),
                            'currency' => $t->string($c, 10)->default('XOF'),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('ecommerce_rfqs')) {
            Schema::table('ecommerce_rfqs', function (Blueprint $t) {
                foreach (['buyer_id','vendor_id','reference','deadline','notes','is_urgent'] as $c) {
                    if (!Schema::hasColumn('ecommerce_rfqs', $c)) {
                        match($c) {
                            'buyer_id','vendor_id' => $t->unsignedBigInteger($c)->nullable(),
                            'reference','notes' => $t->string($c)->nullable(),
                            'deadline' => $t->date($c)->nullable(),
                            'is_urgent' => $t->boolean($c)->default(false),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('ecommerce_rmas')) {
            Schema::table('ecommerce_rmas', function (Blueprint $t) {
                foreach (['order_id','customer_id','reference','reason','resolution','approved_by','approved_at'] as $c) {
                    if (!Schema::hasColumn('ecommerce_rmas', $c)) {
                        match($c) {
                            'order_id','customer_id','approved_by' => $t->unsignedBigInteger($c)->nullable(),
                            'reference','reason','resolution' => $t->string($c)->nullable(),
                            'approved_at' => $t->timestamp($c)->nullable(),
                        };
                    }
                }
            });
        }

        // ── ACHATS (stub fixes) ───────────────────────────────────────────
        if (Schema::hasTable('achats_rfqs')) {
            Schema::table('achats_rfqs', function (Blueprint $t) {
                foreach (['title','description','category','is_urgent','created_by'] as $c) {
                    if (!Schema::hasColumn('achats_rfqs', $c)) {
                        match($c) {
                            'title','description','category' => $t->string($c)->nullable(),
                            'is_urgent' => $t->boolean($c)->default(false),
                            'created_by' => $t->unsignedBigInteger($c)->nullable(),
                        };
                    }
                }
            });
        }

        // ── BI (stub fixes) ───────────────────────────────────────────────
        if (Schema::hasTable('bi_kpis')) {
            Schema::table('bi_kpis', function (Blueprint $t) {
                foreach (['metric','value','period','module','target','unit'] as $c) {
                    if (!Schema::hasColumn('bi_kpis', $c)) {
                        match($c) {
                            'metric','period','module','unit' => $t->string($c)->nullable(),
                            'value','target' => $t->decimal($c, 15, 4)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('bi_dashboards')) {
            Schema::table('bi_dashboards', function (Blueprint $t) {
                foreach (['name','description','layout','is_public','created_by'] as $c) {
                    if (!Schema::hasColumn('bi_dashboards', $c)) {
                        match($c) {
                            'name','description' => $t->string($c)->nullable(),
                            'layout' => $t->text($c)->nullable(),
                            'is_public' => $t->boolean($c)->default(false),
                            'created_by' => $t->unsignedBigInteger($c)->nullable(),
                        };
                    }
                }
            });
        }

        // ── PROJECTS (stub fixes) ─────────────────────────────────────────
        if (Schema::hasTable('prj_sprints')) {
            Schema::table('prj_sprints', function (Blueprint $t) {
                foreach (['project_id','name','goal','start_date','end_date','velocity'] as $c) {
                    if (!Schema::hasColumn('prj_sprints', $c)) {
                        match($c) {
                            'project_id' => $t->unsignedBigInteger($c)->nullable(),
                            'name','goal' => $t->string($c)->nullable(),
                            'start_date','end_date' => $t->date($c)->nullable(),
                            'velocity' => $t->integer($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('prj_team_members')) {
            Schema::table('prj_team_members', function (Blueprint $t) {
                foreach (['project_id','user_id','role','allocation_percent','joined_at'] as $c) {
                    if (!Schema::hasColumn('prj_team_members', $c)) {
                        match($c) {
                            'project_id','user_id' => $t->unsignedBigInteger($c)->nullable(),
                            'role' => $t->string($c)->nullable(),
                            'allocation_percent' => $t->integer($c)->default(100),
                            'joined_at' => $t->timestamp($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('prj_resource_allocations')) {
            Schema::table('prj_resource_allocations', function (Blueprint $t) {
                foreach (['project_id','user_id','task_id','hours_allocated','date_from','date_to'] as $c) {
                    if (!Schema::hasColumn('prj_resource_allocations', $c)) {
                        match($c) {
                            'project_id','user_id','task_id' => $t->unsignedBigInteger($c)->nullable(),
                            'hours_allocated' => $t->decimal($c, 8, 2)->default(0),
                            'date_from','date_to' => $t->date($c)->nullable(),
                        };
                    }
                }
            });
        }

        // ── PLANNING (stub fixes) ─────────────────────────────────────────
        if (Schema::hasTable('planning_shifts')) {
            Schema::table('planning_shifts', function (Blueprint $t) {
                foreach (['name','start_time','end_time','department_id','is_active','color'] as $c) {
                    if (!Schema::hasColumn('planning_shifts', $c)) {
                        match($c) {
                            'name','color' => $t->string($c)->nullable(),
                            'start_time','end_time' => $t->time($c)->nullable(),
                            'department_id' => $t->unsignedBigInteger($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('planning_employee_schedules')) {
            Schema::table('planning_employee_schedules', function (Blueprint $t) {
                foreach (['employee_id','shift_id','date','start_time','end_time','is_confirmed'] as $c) {
                    if (!Schema::hasColumn('planning_employee_schedules', $c)) {
                        match($c) {
                            'employee_id','shift_id' => $t->unsignedBigInteger($c)->nullable(),
                            'date' => $t->date($c)->nullable(),
                            'start_time','end_time' => $t->time($c)->nullable(),
                            'is_confirmed' => $t->boolean($c)->default(false),
                        };
                    }
                }
            });
        }

        // ── VALIDATION (stub fixes) ────────────────────────────────────────
        if (Schema::hasTable('validation_approval_workflows')) {
            Schema::table('validation_approval_workflows', function (Blueprint $t) {
                foreach (['name','module','resource_type','is_active','threshold_amount','currency'] as $c) {
                    if (!Schema::hasColumn('validation_approval_workflows', $c)) {
                        match($c) {
                            'name','module','resource_type','currency' => $t->string($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'threshold_amount' => $t->decimal($c, 15, 4)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('validation_approval_requests')) {
            Schema::table('validation_approval_requests', function (Blueprint $t) {
                foreach (['workflow_id','resource_type','resource_id','requested_by','amount','currency',
                    'current_level','total_levels'] as $c) {
                    if (!Schema::hasColumn('validation_approval_requests', $c)) {
                        match($c) {
                            'workflow_id','resource_id','requested_by' => $t->unsignedBigInteger($c)->nullable(),
                            'resource_type','currency' => $t->string($c)->nullable(),
                            'amount' => $t->decimal($c, 15, 4)->nullable(),
                            'current_level','total_levels' => $t->integer($c)->default(1),
                        };
                    }
                }
            });
        }

        // ── WHATSAPP (stub fixes) ─────────────────────────────────────────
        if (Schema::hasTable('wa_templates')) {
            Schema::table('wa_templates', function (Blueprint $t) {
                foreach (['name','category','language','components','wa_template_id','is_approved'] as $c) {
                    if (!Schema::hasColumn('wa_templates', $c)) {
                        match($c) {
                            'name','category','language','wa_template_id' => $t->string($c)->nullable(),
                            'components' => $t->text($c)->nullable(),
                            'is_approved' => $t->boolean($c)->default(false),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('wa_broadcast_campaigns')) {
            Schema::table('wa_broadcast_campaigns', function (Blueprint $t) {
                foreach (['name','template_id','audience_type','audience_data','scheduled_at',
                    'sent_count','failed_count','created_by'] as $c) {
                    if (!Schema::hasColumn('wa_broadcast_campaigns', $c)) {
                        match($c) {
                            'name','audience_type' => $t->string($c)->nullable(),
                            'template_id','created_by' => $t->unsignedBigInteger($c)->nullable(),
                            'audience_data' => $t->text($c)->nullable(),
                            'scheduled_at' => $t->timestamp($c)->nullable(),
                            'sent_count','failed_count' => $t->integer($c)->default(0),
                        };
                    }
                }
            });
        }

        // ── WORKFLOW (stub fixes) ─────────────────────────────────────────
        if (Schema::hasTable('wfd_definitions')) {
            Schema::table('wfd_definitions', function (Blueprint $t) {
                foreach (['name','description','is_active','trigger_type','nodes','edges','created_by'] as $c) {
                    if (!Schema::hasColumn('wfd_definitions', $c)) {
                        match($c) {
                            'name','description','trigger_type' => $t->string($c)->nullable(),
                            'is_active' => $t->boolean($c)->default(true),
                            'nodes','edges' => $t->text($c)->nullable(),
                            'created_by' => $t->unsignedBigInteger($c)->nullable(),
                        };
                    }
                }
            });
        }
        if (Schema::hasTable('wfd_executions')) {
            Schema::table('wfd_executions', function (Blueprint $t) {
                foreach (['definition_id','trigger_data','started_at','completed_at','error_message'] as $c) {
                    if (!Schema::hasColumn('wfd_executions', $c)) {
                        match($c) {
                            'definition_id' => $t->unsignedBigInteger($c)->nullable(),
                            'trigger_data','error_message' => $t->text($c)->nullable(),
                            'started_at','completed_at' => $t->timestamp($c)->nullable(),
                        };
                    }
                }
            });
        }
    }

    public function down(): void {}
};
