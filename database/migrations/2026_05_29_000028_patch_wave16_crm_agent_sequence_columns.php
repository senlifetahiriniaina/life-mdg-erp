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
        // ── CRM: crm_ai_agent_runs ────────────────────────────────────────
        $this->patch('crm_ai_agent_runs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'agent_id'))           $t->unsignedBigInteger('agent_id')->nullable();
            if (!Schema::hasColumn($table, 'entity_type'))        $t->string('entity_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'entity_id'))          $t->unsignedBigInteger('entity_id')->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'result'))             $t->text('result')->nullable();
            if (!Schema::hasColumn($table, 'error_message'))      $t->text('error_message')->nullable();
            if (!Schema::hasColumn($table, 'executed_at'))        $t->timestamp('executed_at')->nullable();
            if (!Schema::hasColumn($table, 'duration_ms'))        $t->integer('duration_ms')->nullable();
        });

        // ── CRM: crm_activities ───────────────────────────────────────────
        $this->patch('crm_activities', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))              $t->string('title', 200)->nullable();
            if (!Schema::hasColumn($table, 'user_id'))            $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 30)->default('note');
            if (!Schema::hasColumn($table, 'subject_type'))       $t->string('subject_type', 100)->nullable();
            if (!Schema::hasColumn($table, 'subject_id'))         $t->unsignedBigInteger('subject_id')->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'due_date'))           $t->dateTime('due_date')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))       $t->timestamp('completed_at')->nullable();
        });

        // ── CRM: crm_sequence_enrollments ────────────────────────────────
        $this->patch('crm_sequence_enrollments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sequence_id'))        $t->unsignedBigInteger('sequence_id')->nullable();
            if (!Schema::hasColumn($table, 'contact_id'))         $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('active');
            if (!Schema::hasColumn($table, 'current_step'))       $t->integer('current_step')->default(0);
            if (!Schema::hasColumn($table, 'enrolled_at'))        $t->timestamp('enrolled_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))       $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'enrolled_by'))        $t->unsignedBigInteger('enrolled_by')->nullable();
        });

        // ── CRM: crm_leads ────────────────────────────────────────────────
        $this->patch('crm_leads', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))              $t->string('title', 200)->nullable();
            if (!Schema::hasColumn($table, 'source'))             $t->string('source', 50)->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 30)->default('new');
            if (!Schema::hasColumn($table, 'score'))              $t->integer('score')->default(0);
            if (!Schema::hasColumn($table, 'assigned_to'))        $t->unsignedBigInteger('assigned_to')->nullable();
            if (!Schema::hasColumn($table, 'converted_at'))       $t->timestamp('converted_at')->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
        });

        // ── CRM: crm_web_form_submissions ────────────────────────────────
        $this->patch('crm_web_form_submissions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'form_id'))            $t->unsignedBigInteger('form_id')->nullable();
            if (!Schema::hasColumn($table, 'form_data'))          $t->text('form_data')->nullable();
            if (!Schema::hasColumn($table, 'ip_address'))         $t->string('ip_address', 50)->nullable();
            if (!Schema::hasColumn($table, 'lead_id'))            $t->unsignedBigInteger('lead_id')->nullable();
            if (!Schema::hasColumn($table, 'contact_id'))         $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'processed_at'))       $t->timestamp('processed_at')->nullable();
        });

        // ── CRM: crm_email_sequences ──────────────────────────────────────
        $this->patch('crm_email_sequences', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'trigger_event'))      $t->string('trigger_event', 50)->nullable();
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── Workflow: wfd_templates ───────────────────────────────────────
        $this->patch('wfd_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'key'))                $t->string('key', 100)->nullable();
            if (!Schema::hasColumn($table, 'category'))           $t->string('category', 50)->nullable();
            if (!Schema::hasColumn($table, 'trigger_type'))       $t->string('trigger_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'nodes'))              $t->text('nodes')->nullable();
            if (!Schema::hasColumn($table, 'edges'))              $t->text('edges')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'usage_count'))        $t->integer('usage_count')->default(0);
        });

        // ── Workflow: wfd_schedules ───────────────────────────────────────
        $this->patch('wfd_schedules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'flow_id'))            $t->unsignedBigInteger('flow_id')->nullable();
            if (!Schema::hasColumn($table, 'cron_expression'))    $t->string('cron_expression', 100)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'last_run_at'))        $t->timestamp('last_run_at')->nullable();
            if (!Schema::hasColumn($table, 'next_run_at'))        $t->timestamp('next_run_at')->nullable();
        });

        // ── Workflow: wfd_conditions ──────────────────────────────────────
        $this->patch('wfd_conditions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'flow_id'))            $t->unsignedBigInteger('flow_id')->nullable();
            if (!Schema::hasColumn($table, 'node_id'))            $t->string('node_id', 100)->nullable();
            if (!Schema::hasColumn($table, 'field'))              $t->string('field', 100)->nullable();
            if (!Schema::hasColumn($table, 'operator'))           $t->string('operator', 30)->nullable();
            if (!Schema::hasColumn($table, 'value'))              $t->text('value')->nullable();
            if (!Schema::hasColumn($table, 'logic'))              $t->string('logic', 10)->default('AND');
        });

        // ── Inventory: inv_stock_levels ───────────────────────────────────
        $this->patch('inv_stock_levels', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'location_id'))        $t->unsignedBigInteger('location_id')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))           $t->decimal('quantity', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'reserved_qty'))       $t->decimal('reserved_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'available_qty'))      $t->decimal('available_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
            if (!Schema::hasColumn($table, 'expiry_date'))        $t->date('expiry_date')->nullable();
        });

        // ── Inventory: inv_receipts ───────────────────────────────────────
        $this->patch('inv_receipts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'purchase_order_id'))  $t->unsignedBigInteger('purchase_order_id')->nullable();
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'received_by'))        $t->unsignedBigInteger('received_by')->nullable();
            if (!Schema::hasColumn($table, 'received_at'))        $t->timestamp('received_at')->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
        });

        // ── Inventory: inv_receipt_lines ──────────────────────────────────
        $this->patch('inv_receipt_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'receipt_id'))         $t->unsignedBigInteger('receipt_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'ordered_qty'))        $t->decimal('ordered_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'received_qty'))       $t->decimal('received_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
            if (!Schema::hasColumn($table, 'expiry_date'))        $t->date('expiry_date')->nullable();
            if (!Schema::hasColumn($table, 'location_id'))        $t->unsignedBigInteger('location_id')->nullable();
        });
    }

    public function down(): void {}
};
