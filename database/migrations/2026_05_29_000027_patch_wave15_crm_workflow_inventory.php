<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // ── CRM: crm_opportunity_scores ───────────────────────────────────
        $this->patch('crm_opportunity_scores', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'grade'))              $t->string('grade', 5)->default('F');
            if (!Schema::hasColumn($table, 'engagement_score'))   $t->integer('engagement_score')->default(0);
            if (!Schema::hasColumn($table, 'fit_score'))          $t->integer('fit_score')->default(0);
            if (!Schema::hasColumn($table, 'velocity_score'))     $t->integer('velocity_score')->default(0);
            if (!Schema::hasColumn($table, 'history_score'))      $t->integer('history_score')->default(0);
            if (!Schema::hasColumn($table, 'win_probability'))    $t->decimal('win_probability', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'score_breakdown'))    $t->text('score_breakdown')->nullable();
            if (!Schema::hasColumn($table, 'signals_used'))       $t->text('signals_used')->nullable();
            if (!Schema::hasColumn($table, 'total_score'))        $t->decimal('total_score', 8, 4)->default(0);
            if (!Schema::hasColumn($table, 'scored_at'))          $t->timestamp('scored_at')->nullable();
        });

        // ── CRM: crm_scoring_rules ────────────────────────────────────────
        $this->patch('crm_scoring_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'category'))           $t->string('category', 50)->default('fit');
            if (!Schema::hasColumn($table, 'weight'))             $t->integer('weight')->default(1);
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'sort_order'))         $t->integer('sort_order')->default(0);
        });

        // ── CRM: crm_engagement_signals ──────────────────────────────────
        $this->patch('crm_engagement_signals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'opportunity_id'))     $t->unsignedBigInteger('opportunity_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'signal_type'))        $t->string('signal_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'score_impact'))       $t->integer('score_impact')->default(0);
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'source'))             $t->string('source', 50)->default('manual');
            if (!Schema::hasColumn($table, 'occurred_at'))        $t->timestamp('occurred_at')->nullable();
            if (!Schema::hasColumn($table, 'activity_id'))        $t->unsignedBigInteger('activity_id')->nullable();
        });

        // ── CRM: crm_call_logs ────────────────────────────────────────────
        $this->patch('crm_call_logs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_id'))            $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'direction'))          $t->string('direction', 20)->default('outbound');
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('answered');
            if (!Schema::hasColumn($table, 'phone_number'))       $t->string('phone_number', 50)->nullable();
            if (!Schema::hasColumn($table, 'called_at'))          $t->timestamp('called_at')->nullable();
            if (!Schema::hasColumn($table, 'contact_id'))         $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'lead_id'))            $t->unsignedBigInteger('lead_id')->nullable();
            if (!Schema::hasColumn($table, 'duration'))           $t->integer('duration')->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'recording_url'))      $t->string('recording_url')->nullable();
        });

        // ── CRM: crm_quotes ───────────────────────────────────────────────
        $this->patch('crm_quotes', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'subtotal'))           $t->decimal('subtotal', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'discount_amount'))    $t->decimal('discount_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'tax_amount'))         $t->decimal('tax_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'total'))              $t->decimal('total', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'opportunity_id'))     $t->unsignedBigInteger('opportunity_id')->nullable();
            if (!Schema::hasColumn($table, 'contact_id'))         $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 30)->default('draft');
            if (!Schema::hasColumn($table, 'valid_until'))        $t->date('valid_until')->nullable();
        });

        // ── CRM: crm_web_forms ────────────────────────────────────────────
        $this->patch('crm_web_forms', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'create_lead'))        $t->boolean('create_lead')->default(true);
        });

        // ── CRM: crm_ai_agents ────────────────────────────────────────────
        $this->patch('crm_ai_agents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'action_config'))      $t->text('action_config')->nullable();
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'trigger_type'))       $t->string('trigger_type', 50)->default('schedule');
            if (!Schema::hasColumn($table, 'trigger_config'))     $t->text('trigger_config')->nullable();
            if (!Schema::hasColumn($table, 'action_type'))        $t->string('action_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'conditions'))         $t->text('conditions')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'last_run_at'))        $t->timestamp('last_run_at')->nullable();
            if (!Schema::hasColumn($table, 'run_count'))          $t->integer('run_count')->default(0);
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── CRM: crm_territories ─────────────────────────────────────────
        $this->patch('crm_territories', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'parent_id'))          $t->unsignedBigInteger('parent_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 30)->default('geographic');
            if (!Schema::hasColumn($table, 'criteria'))           $t->text('criteria')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── CRM: crm_territory_assignments ───────────────────────────────
        $this->patch('crm_territory_assignments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'territory_id'))       $t->unsignedBigInteger('territory_id')->nullable();
            if (!Schema::hasColumn($table, 'user_id'))            $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'role'))               $t->string('role', 30)->default('member');
            if (!Schema::hasColumn($table, 'assigned_at'))        $t->timestamp('assigned_at')->nullable();
        });

        // ── CRM: crm_win_loss_records ─────────────────────────────────────
        $this->patch('crm_win_loss_records', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'opportunity_id'))     $t->unsignedBigInteger('opportunity_id')->nullable();
            if (!Schema::hasColumn($table, 'outcome'))            $t->string('outcome', 20)->default('lost');
            if (!Schema::hasColumn($table, 'reason'))             $t->string('reason', 100)->nullable();
            if (!Schema::hasColumn($table, 'competitor'))         $t->string('competitor', 100)->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'recorded_by'))        $t->unsignedBigInteger('recorded_by')->nullable();
            if (!Schema::hasColumn($table, 'recorded_at'))        $t->timestamp('recorded_at')->nullable();
        });

        // ── CRM: crm_pipeline_snapshots ───────────────────────────────────
        $this->patch('crm_pipeline_snapshots', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'pipeline_id'))        $t->unsignedBigInteger('pipeline_id')->nullable();
            if (!Schema::hasColumn($table, 'snapshot_date'))      $t->date('snapshot_date')->nullable();
            if (!Schema::hasColumn($table, 'stage_data'))         $t->text('stage_data')->nullable();
            if (!Schema::hasColumn($table, 'total_value'))        $t->decimal('total_value', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'deal_count'))         $t->integer('deal_count')->default(0);
        });

        // ── Workflow: wfd_actions ─────────────────────────────────────────
        $this->patch('wfd_actions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'action_config'))      $t->text('action_config')->nullable();
            if (!Schema::hasColumn($table, 'node_id'))            $t->string('node_id', 100)->nullable();
            if (!Schema::hasColumn($table, 'flow_id'))            $t->unsignedBigInteger('flow_id')->nullable();
            if (!Schema::hasColumn($table, 'position_x'))         $t->integer('position_x')->default(0);
            if (!Schema::hasColumn($table, 'position_y'))         $t->integer('position_y')->default(0);
            if (!Schema::hasColumn($table, 'config'))             $t->text('config')->nullable();
            if (!Schema::hasColumn($table, 'sort_order'))         $t->integer('sort_order')->default(0);
        });

        // ── Workflow: wfd_flows ───────────────────────────────────────────
        $this->patch('wfd_flows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'key'))                $t->string('key', 100)->nullable();
            if (!Schema::hasColumn($table, 'trigger_type'))       $t->string('trigger_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'trigger_config'))     $t->text('trigger_config')->nullable();
            if (!Schema::hasColumn($table, 'nodes'))              $t->text('nodes')->nullable();
            if (!Schema::hasColumn($table, 'edges'))              $t->text('edges')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── Workflow: wfd_executions ──────────────────────────────────────
        $this->patch('wfd_executions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'flow_id'))            $t->unsignedBigInteger('flow_id')->nullable();
            if (!Schema::hasColumn($table, 'trigger_data'))       $t->text('trigger_data')->nullable();
            if (!Schema::hasColumn($table, 'context'))            $t->text('context')->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'started_at'))         $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))       $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'error_message'))      $t->text('error_message')->nullable();
            if (!Schema::hasColumn($table, 'node_results'))       $t->text('node_results')->nullable();
        });

        // ── Workflow: wfd_node_executions ─────────────────────────────────
        $this->patch('wfd_node_executions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'execution_id'))       $t->unsignedBigInteger('execution_id')->nullable();
            if (!Schema::hasColumn($table, 'node_id'))            $t->string('node_id', 100)->nullable();
            if (!Schema::hasColumn($table, 'node_type'))          $t->string('node_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'input_data'))         $t->text('input_data')->nullable();
            if (!Schema::hasColumn($table, 'output_data'))        $t->text('output_data')->nullable();
            if (!Schema::hasColumn($table, 'error_message'))      $t->text('error_message')->nullable();
            if (!Schema::hasColumn($table, 'started_at'))         $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))       $t->timestamp('completed_at')->nullable();
        });

        // ── Workflow: automation_rules ────────────────────────────────────
        $this->patch('automation_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'key'))                $t->string('key', 100)->nullable();
            if (!Schema::hasColumn($table, 'module'))             $t->string('module', 50)->nullable();
            if (!Schema::hasColumn($table, 'trigger_event'))      $t->string('trigger_event', 100)->nullable();
            if (!Schema::hasColumn($table, 'conditions'))         $t->text('conditions')->nullable();
            if (!Schema::hasColumn($table, 'actions'))            $t->text('actions')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── Inventory: inv_warehouses ─────────────────────────────────────
        $this->patch('inv_warehouses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'code'))               $t->string('code', 20)->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 30)->default('standard');
            if (!Schema::hasColumn($table, 'address'))            $t->text('address')->nullable();
            if (!Schema::hasColumn($table, 'capacity'))           $t->decimal('capacity', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'manager_id'))         $t->unsignedBigInteger('manager_id')->nullable();
        });

        // ── Inventory: inv_warehouse_zones ────────────────────────────────
        $this->patch('inv_warehouse_zones', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'code'))               $t->string('code', 20)->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 30)->default('storage');
            if (!Schema::hasColumn($table, 'capacity'))           $t->decimal('capacity', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'temperature_min'))    $t->decimal('temperature_min', 5, 2)->nullable();
            if (!Schema::hasColumn($table, 'temperature_max'))    $t->decimal('temperature_max', 5, 2)->nullable();
        });

        // ── Inventory: inv_locations ──────────────────────────────────────
        $this->patch('inv_locations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'zone_id'))            $t->unsignedBigInteger('zone_id')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'aisle'))              $t->string('aisle', 20)->nullable();
            if (!Schema::hasColumn($table, 'rack'))               $t->string('rack', 20)->nullable();
            if (!Schema::hasColumn($table, 'level'))              $t->string('level', 20)->nullable();
            if (!Schema::hasColumn($table, 'bin'))                $t->string('bin', 20)->nullable();
            if (!Schema::hasColumn($table, 'capacity'))           $t->decimal('capacity', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
        });

        // ── Inventory: inv_stock_movements ────────────────────────────────
        $this->patch('inv_stock_movements', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'from_location_id'))   $t->unsignedBigInteger('from_location_id')->nullable();
            if (!Schema::hasColumn($table, 'to_location_id'))     $t->unsignedBigInteger('to_location_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 30)->default('in');
            if (!Schema::hasColumn($table, 'quantity'))           $t->decimal('quantity', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'unit_cost'))          $t->decimal('unit_cost', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 100)->nullable();
            if (!Schema::hasColumn($table, 'reference_type'))     $t->string('reference_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'reference_id'))       $t->unsignedBigInteger('reference_id')->nullable();
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
            if (!Schema::hasColumn($table, 'serial_number'))      $t->string('serial_number', 100)->nullable();
            if (!Schema::hasColumn($table, 'expiry_date'))        $t->date('expiry_date')->nullable();
            if (!Schema::hasColumn($table, 'performed_by'))       $t->unsignedBigInteger('performed_by')->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
        });

        // ── Inventory: inv_adjustments ────────────────────────────────────
        $this->patch('inv_adjustments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 30)->default('manual');
            if (!Schema::hasColumn($table, 'reason'))             $t->string('reason', 100)->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'approved_by'))        $t->unsignedBigInteger('approved_by')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))        $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── Inventory: inv_adjustment_lines ───────────────────────────────
        $this->patch('inv_adjustment_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'adjustment_id'))      $t->unsignedBigInteger('adjustment_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'location_id'))        $t->unsignedBigInteger('location_id')->nullable();
            if (!Schema::hasColumn($table, 'expected_qty'))       $t->decimal('expected_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'actual_qty'))         $t->decimal('actual_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'difference'))         $t->decimal('difference', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
        });

        // ── Inventory: inv_suppliers ──────────────────────────────────────
        $this->patch('inv_suppliers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'code'))               $t->string('code', 30)->nullable();
            if (!Schema::hasColumn($table, 'email'))              $t->string('email', 150)->nullable();
            if (!Schema::hasColumn($table, 'phone'))              $t->string('phone', 50)->nullable();
            if (!Schema::hasColumn($table, 'country'))            $t->string('country', 5)->nullable();
            if (!Schema::hasColumn($table, 'currency'))           $t->string('currency', 5)->default('XOF');
            if (!Schema::hasColumn($table, 'payment_terms'))      $t->integer('payment_terms')->default(30);
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'rating'))             $t->decimal('rating', 3, 1)->default(0);
        });

        // ── Inventory: inv_purchase_orders ────────────────────────────────
        $this->patch('inv_purchase_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'supplier_id'))        $t->unsignedBigInteger('supplier_id')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'expected_date'))      $t->date('expected_date')->nullable();
            if (!Schema::hasColumn($table, 'total_amount'))       $t->decimal('total_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'currency'))           $t->string('currency', 5)->default('XOF');
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'approved_by'))        $t->unsignedBigInteger('approved_by')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))        $t->timestamp('approved_at')->nullable();
        });

        // ── Inventory: inv_purchase_order_lines ───────────────────────────
        $this->patch('inv_purchase_order_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'purchase_order_id'))  $t->unsignedBigInteger('purchase_order_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'ordered_qty'))        $t->decimal('ordered_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'received_qty'))       $t->decimal('received_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'unit_price'))         $t->decimal('unit_price', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'tax_rate'))           $t->decimal('tax_rate', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'total_price'))        $t->decimal('total_price', 15, 2)->default(0);
        });

        // ── Inventory: inv_product_variants ──────────────────────────────
        $this->patch('inv_product_variants', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'sku'))                $t->string('sku', 100)->nullable();
            if (!Schema::hasColumn($table, 'attributes'))         $t->text('attributes')->nullable();
            if (!Schema::hasColumn($table, 'price_modifier'))     $t->decimal('price_modifier', 10, 2)->default(0);
            if (!Schema::hasColumn($table, 'stock_qty'))          $t->decimal('stock_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void {}
};
