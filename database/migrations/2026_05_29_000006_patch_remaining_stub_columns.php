<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, array $cols): void
    {
        if (!Schema::hasTable($table)) return;
        Schema::table($table, function (Blueprint $t) use ($table, $cols) {
            foreach ($cols as $col => $def) {
                if (!Schema::hasColumn($table, $col)) {
                    $def($t);
                }
            }
        });
    }

    public function up(): void
    {
        // mfg_boms (distinct from mfg_bill_of_materials)
        $this->patch('mfg_boms', [
            'name'        => fn($t) => $t->string('name')->nullable(),
            'product_id'  => fn($t) => $t->unsignedBigInteger('product_id')->nullable(),
            'version'     => fn($t) => $t->string('version')->nullable(),
            'type'        => fn($t) => $t->string('type')->nullable(),
            'quantity'    => fn($t) => $t->decimal('quantity', 12, 4)->default(1),
            'is_active'   => fn($t) => $t->boolean('is_active')->default(true),
            'is_default'  => fn($t) => $t->boolean('is_default')->default(false),
            'notes'       => fn($t) => $t->text('notes')->nullable(),
            'approved_by' => fn($t) => $t->unsignedBigInteger('approved_by')->nullable(),
            'approved_at' => fn($t) => $t->timestamp('approved_at')->nullable(),
        ]);

        // mfg_work_orders (add reference + more columns)
        $this->patch('mfg_work_orders', [
            'reference'         => fn($t) => $t->string('reference')->nullable(),
            'production_order_id' => fn($t) => $t->unsignedBigInteger('production_order_id')->nullable(),
            'workcenter_id'     => fn($t) => $t->unsignedBigInteger('workcenter_id')->nullable(),
            'quantity'          => fn($t) => $t->decimal('quantity', 12, 4)->default(0),
            'quantity_done'     => fn($t) => $t->decimal('quantity_done', 12, 4)->default(0),
            'planned_start'     => fn($t) => $t->timestamp('planned_start')->nullable(),
            'planned_end'       => fn($t) => $t->timestamp('planned_end')->nullable(),
        ]);

        // mfg_workcenters (add name)
        $this->patch('mfg_workcenters', [
            'name'         => fn($t) => $t->string('name')->nullable(),
            'code'         => fn($t) => $t->string('code')->nullable(),
            'capacity'     => fn($t) => $t->decimal('capacity', 12, 4)->nullable(),
            'efficiency'   => fn($t) => $t->decimal('efficiency', 5, 2)->default(100),
            'hourly_cost'  => fn($t) => $t->decimal('hourly_cost', 15, 4)->nullable(),
            'is_active'    => fn($t) => $t->boolean('is_active')->default(true),
            'currency'     => fn($t) => $t->string('currency', 10)->default('XOF'),
        ]);

        // mfg_mrp_runs (add name)
        $this->patch('mfg_mrp_runs', [
            'name'          => fn($t) => $t->string('name')->nullable(),
            'run_date'      => fn($t) => $t->date('run_date')->nullable(),
            'horizon_days'  => fn($t) => $t->integer('horizon_days')->default(30),
            'created_by'    => fn($t) => $t->unsignedBigInteger('created_by')->nullable(),
        ]);

        // mfg_bill_of_materials (add quantity)
        $this->patch('mfg_bill_of_materials', [
            'quantity'   => fn($t) => $t->decimal('quantity', 12, 4)->default(1),
            'product_id' => fn($t) => $t->unsignedBigInteger('product_id')->nullable(),
            'type'       => fn($t) => $t->string('type')->nullable(),
            'is_active'  => fn($t) => $t->boolean('is_active')->default(true),
            'notes'      => fn($t) => $t->text('notes')->nullable(),
        ]);

        // crm_email_sequences (add name)
        $this->patch('crm_email_sequences', [
            'name'         => fn($t) => $t->string('name')->nullable(),
            'description'  => fn($t) => $t->text('description')->nullable(),
            'trigger_type' => fn($t) => $t->string('trigger_type')->nullable(),
            'is_active'    => fn($t) => $t->boolean('is_active')->default(true),
            'created_by'   => fn($t) => $t->unsignedBigInteger('created_by')->nullable(),
        ]);

        // crm_ai_agents (add name)
        $this->patch('crm_ai_agents', [
            'name'         => fn($t) => $t->string('name')->nullable(),
            'trigger_type' => fn($t) => $t->string('trigger_type')->nullable(),
            'action_type'  => fn($t) => $t->string('action_type')->nullable(),
            'config'       => fn($t) => $t->text('config')->nullable(),
            'is_active'    => fn($t) => $t->boolean('is_active')->default(true),
            'created_by'   => fn($t) => $t->unsignedBigInteger('created_by')->nullable(),
        ]);

        // crm_win_loss_records
        $this->patch('crm_win_loss_records', [
            'opportunity_id' => fn($t) => $t->unsignedBigInteger('opportunity_id')->nullable(),
            'outcome'        => fn($t) => $t->string('outcome')->nullable(),
            'reason'         => fn($t) => $t->string('reason')->nullable(),
            'deal_value'     => fn($t) => $t->decimal('deal_value', 15, 4)->nullable(),
            'competitor'     => fn($t) => $t->string('competitor')->nullable(),
        ]);

        // hr_job_postings (add title)
        $this->patch('hr_job_postings', [
            'title'        => fn($t) => $t->string('title')->nullable(),
            'department_id'=> fn($t) => $t->unsignedBigInteger('department_id')->nullable(),
            'location'     => fn($t) => $t->string('location')->nullable(),
            'type'         => fn($t) => $t->string('type')->nullable(),
            'description'  => fn($t) => $t->text('description')->nullable(),
            'is_published' => fn($t) => $t->boolean('is_published')->default(false),
            'created_by'   => fn($t) => $t->unsignedBigInteger('created_by')->nullable(),
        ]);

        // hr_review_cycles
        $this->patch('hr_review_cycles', [
            'name'       => fn($t) => $t->string('name')->nullable(),
            'start_date' => fn($t) => $t->date('start_date')->nullable(),
            'end_date'   => fn($t) => $t->date('end_date')->nullable(),
            'type'       => fn($t) => $t->string('type')->nullable(),
            'is_active'  => fn($t) => $t->boolean('is_active')->default(true),
        ]);

        // hr_appraisal_cycles
        $this->patch('hr_appraisal_cycles', [
            'name'       => fn($t) => $t->string('name')->nullable(),
            'start_date' => fn($t) => $t->date('start_date')->nullable(),
            'end_date'   => fn($t) => $t->date('end_date')->nullable(),
            'type'       => fn($t) => $t->string('type')->nullable(),
            'is_active'  => fn($t) => $t->boolean('is_active')->default(true),
        ]);

        // hr_succession_plans (add name)
        $this->patch('hr_succession_plans', [
            'name'          => fn($t) => $t->string('name')->nullable(),
            'position_id'   => fn($t) => $t->unsignedBigInteger('position_id')->nullable(),
            'incumbent_id'  => fn($t) => $t->unsignedBigInteger('incumbent_id')->nullable(),
            'created_by'    => fn($t) => $t->unsignedBigInteger('created_by')->nullable(),
            'review_date'   => fn($t) => $t->date('review_date')->nullable(),
            'is_active'     => fn($t) => $t->boolean('is_active')->default(true),
        ]);

        // hr_payslip_lines (add description)
        $this->patch('hr_payslip_lines', [
            'description' => fn($t) => $t->string('description')->nullable(),
            'category'    => fn($t) => $t->string('category')->nullable(),
            'is_taxable'  => fn($t) => $t->boolean('is_taxable')->default(true),
        ]);

        // hd_kb_categories
        $this->patch('hd_kb_categories', [
            'icon'       => fn($t) => $t->string('icon')->nullable(),
            'name'       => fn($t) => $t->string('name')->nullable(),
            'parent_id'  => fn($t) => $t->unsignedBigInteger('parent_id')->nullable(),
            'created_by' => fn($t) => $t->unsignedBigInteger('created_by')->nullable(),
            'is_active'  => fn($t) => $t->boolean('is_active')->default(true),
        ]);

        // hd_sla_policies (add name)
        $this->patch('hd_sla_policies', [
            'name'                  => fn($t) => $t->string('name')->nullable(),
            'first_response_hours'  => fn($t) => $t->integer('first_response_hours')->nullable(),
            'resolution_hours'      => fn($t) => $t->integer('resolution_hours')->nullable(),
            'is_active'             => fn($t) => $t->boolean('is_active')->default(true),
            'priority'              => fn($t) => $t->string('priority')->nullable(),
        ]);

        // hd_tickets (add sla_breached)
        $this->patch('hd_tickets', [
            'sla_breached'  => fn($t) => $t->boolean('sla_breached')->default(false),
            'reporter_id'   => fn($t) => $t->unsignedBigInteger('reporter_id')->nullable(),
            'channel'       => fn($t) => $t->string('channel')->nullable(),
            'sla_due_at'    => fn($t) => $t->timestamp('sla_due_at')->nullable(),
        ]);

        // inventory_lots (add lot_number)
        $this->patch('inventory_lots', [
            'lot_number'  => fn($t) => $t->string('lot_number')->nullable(),
            'product_id'  => fn($t) => $t->unsignedBigInteger('product_id')->nullable(),
            'quantity'    => fn($t) => $t->decimal('quantity', 12, 4)->default(0),
            'expiry_date' => fn($t) => $t->date('expiry_date')->nullable(),
            'location_id' => fn($t) => $t->unsignedBigInteger('location_id')->nullable(),
        ]);

        // inventory_carriers (add name)
        $this->patch('inventory_carriers', [
            'name'                   => fn($t) => $t->string('name')->nullable(),
            'code'                   => fn($t) => $t->string('code')->nullable(),
            'tracking_url_template'  => fn($t) => $t->string('tracking_url_template', 512)->nullable(),
            'api_key'                => fn($t) => $t->string('api_key', 512)->nullable(),
            'active'                 => fn($t) => $t->boolean('active')->default(true),
            'settings'               => fn($t) => $t->text('settings')->nullable(),
        ]);

        // inventory_cost_layers
        $this->patch('inventory_cost_layers', [
            'product_id'     => fn($t) => $t->unsignedBigInteger('product_id')->nullable(),
            'warehouse_id'   => fn($t) => $t->unsignedBigInteger('warehouse_id')->nullable(),
            'quantity'       => fn($t) => $t->decimal('quantity', 12, 4)->default(0),
            'unit_cost'      => fn($t) => $t->decimal('unit_cost', 15, 4)->default(0),
            'valuation_type' => fn($t) => $t->string('valuation_type')->default('fifo'),
        ]);

        // inventory_transfer_orders
        $this->patch('inventory_transfer_orders', [
            'reference'          => fn($t) => $t->string('reference')->nullable(),
            'from_warehouse_id'  => fn($t) => $t->unsignedBigInteger('from_warehouse_id')->nullable(),
            'to_warehouse_id'    => fn($t) => $t->unsignedBigInteger('to_warehouse_id')->nullable(),
            'requested_by'       => fn($t) => $t->unsignedBigInteger('requested_by')->nullable(),
        ]);

        // inventory_demand_forecasts
        $this->patch('inventory_demand_forecasts', [
            'product_id'      => fn($t) => $t->unsignedBigInteger('product_id')->nullable(),
            'forecast_date'   => fn($t) => $t->date('forecast_date')->nullable(),
            'forecast_qty'    => fn($t) => $t->decimal('forecast_qty', 12, 4)->default(0),
            'actual_qty'      => fn($t) => $t->decimal('actual_qty', 12, 4)->nullable(),
            'horizon_days'    => fn($t) => $t->integer('horizon_days')->default(30),
        ]);

        // ecommerce_vendors
        $this->patch('ecommerce_vendors', [
            'user_id'          => fn($t) => $t->unsignedBigInteger('user_id')->nullable(),
            'company_name'     => fn($t) => $t->string('company_name')->nullable(),
            'email'            => fn($t) => $t->string('email')->nullable(),
            'phone'            => fn($t) => $t->string('phone')->nullable(),
            'commission_rate'  => fn($t) => $t->decimal('commission_rate', 5, 2)->default(0),
            'is_verified'      => fn($t) => $t->boolean('is_verified')->default(false),
            'is_active'        => fn($t) => $t->boolean('is_active')->default(true),
        ]);

        // ecommerce_rmas
        $this->patch('ecommerce_rmas', [
            'order_id'      => fn($t) => $t->unsignedBigInteger('order_id')->nullable(),
            'customer_id'   => fn($t) => $t->unsignedBigInteger('customer_id')->nullable(),
            'reference'     => fn($t) => $t->string('reference')->nullable(),
            'reason'        => fn($t) => $t->string('reason')->nullable(),
            'resolution'    => fn($t) => $t->string('resolution')->nullable(),
            'approved_by'   => fn($t) => $t->unsignedBigInteger('approved_by')->nullable(),
            'approved_at'   => fn($t) => $t->timestamp('approved_at')->nullable(),
        ]);

        // ecommerce_rfqs
        $this->patch('ecommerce_rfqs', [
            'reference'   => fn($t) => $t->string('reference')->nullable(),
            'buyer_id'    => fn($t) => $t->unsignedBigInteger('buyer_id')->nullable(),
            'vendor_id'   => fn($t) => $t->unsignedBigInteger('vendor_id')->nullable(),
            'deadline'    => fn($t) => $t->date('deadline')->nullable(),
            'notes'       => fn($t) => $t->text('notes')->nullable(),
            'is_urgent'   => fn($t) => $t->boolean('is_urgent')->default(false),
        ]);

        // ecommerce_subscription_plans
        $this->patch('ecommerce_subscription_plans', [
            'name'           => fn($t) => $t->string('name')->nullable(),
            'price'          => fn($t) => $t->decimal('price', 15, 4)->default(0),
            'currency'       => fn($t) => $t->string('currency', 10)->default('XOF'),
            'billing_cycle'  => fn($t) => $t->string('billing_cycle')->default('monthly'),
            'features'       => fn($t) => $t->text('features')->nullable(),
            'is_active'      => fn($t) => $t->boolean('is_active')->default(true),
        ]);

        // ecommerce_product_configurators
        $this->patch('ecommerce_product_configurators', [
            'product_id'  => fn($t) => $t->unsignedBigInteger('product_id')->nullable(),
            'name'        => fn($t) => $t->string('name')->nullable(),
            'steps'       => fn($t) => $t->text('steps')->nullable(),
            'rules'       => fn($t) => $t->text('rules')->nullable(),
            'is_active'   => fn($t) => $t->boolean('is_active')->default(true),
        ]);

        // ecom_promotions
        $this->patch('ecom_promotions', [
            'name'           => fn($t) => $t->string('name')->nullable(),
            'type'           => fn($t) => $t->string('type')->nullable(),
            'value'          => fn($t) => $t->decimal('value', 15, 4)->default(0),
            'starts_at'      => fn($t) => $t->timestamp('starts_at')->nullable(),
            'ends_at'        => fn($t) => $t->timestamp('ends_at')->nullable(),
            'is_active'      => fn($t) => $t->boolean('is_active')->default(true),
            'minimum_amount' => fn($t) => $t->decimal('minimum_amount', 15, 4)->nullable(),
        ]);

        // ecom_shipments
        $this->patch('ecom_shipments', [
            'order_id'         => fn($t) => $t->unsignedBigInteger('order_id')->nullable(),
            'carrier'          => fn($t) => $t->string('carrier')->nullable(),
            'tracking_number'  => fn($t) => $t->string('tracking_number')->nullable(),
            'shipped_at'       => fn($t) => $t->timestamp('shipped_at')->nullable(),
            'delivered_at'     => fn($t) => $t->timestamp('delivered_at')->nullable(),
        ]);

        // ecom_returns
        $this->patch('ecom_returns', [
            'order_id'   => fn($t) => $t->unsignedBigInteger('order_id')->nullable(),
            'reason'     => fn($t) => $t->string('reason')->nullable(),
            'amount'     => fn($t) => $t->decimal('amount', 15, 4)->nullable(),
        ]);

        // ec_carts (add store_id)
        $this->patch('ec_carts', [
            'store_id'    => fn($t) => $t->unsignedBigInteger('store_id')->nullable(),
            'customer_id' => fn($t) => $t->unsignedBigInteger('customer_id')->nullable(),
            'session_id'  => fn($t) => $t->string('session_id')->nullable(),
            'currency'    => fn($t) => $t->string('currency', 10)->default('XOF'),
        ]);

        // logistics_carriers (add name)
        $this->patch('logistics_carriers', [
            'name'          => fn($t) => $t->string('name')->nullable(),
            'code'          => fn($t) => $t->string('code')->nullable(),
            'type'          => fn($t) => $t->string('type')->nullable(),
            'is_active'     => fn($t) => $t->boolean('is_active')->default(true),
            'contact_email' => fn($t) => $t->string('contact_email')->nullable(),
            'contact_phone' => fn($t) => $t->string('contact_phone')->nullable(),
        ]);
    }

    public function down(): void {}
};
