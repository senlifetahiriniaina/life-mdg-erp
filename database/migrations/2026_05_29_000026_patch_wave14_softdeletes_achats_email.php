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
        // ── Quality ───────────────────────────────────────────────────────
        $this->patch('quality_inspections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity_rework'))  $t->integer('quantity_rework')->default(0);
            if (!Schema::hasColumn($table, 'acceptance_rate'))  $t->decimal('acceptance_rate', 5, 2)->nullable();
            if (!Schema::hasColumn($table, 'approved_by'))      $t->unsignedBigInteger('approved_by')->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
            if (!Schema::hasColumn($table, 'scheduled_date'))   $t->timestamp('scheduled_date')->nullable();
            if (!Schema::hasColumn($table, 'started_at'))       $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'inspector_id'))     $t->unsignedBigInteger('inspector_id')->nullable();
        });

        $this->patch('quality_issues', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
            if (!Schema::hasColumn($table, 'resolution_notes')) $t->text('resolution_notes')->nullable();
            if (!Schema::hasColumn($table, 'root_cause'))       $t->text('root_cause')->nullable();
            if (!Schema::hasColumn($table, 'assigned_to'))      $t->unsignedBigInteger('assigned_to')->nullable();
            if (!Schema::hasColumn($table, 'due_date'))         $t->timestamp('due_date')->nullable();
            if (!Schema::hasColumn($table, 'days_open'))        $t->integer('days_open')->default(0);
            if (!Schema::hasColumn($table, 'resolved_at'))      $t->timestamp('resolved_at')->nullable();
            if (!Schema::hasColumn($table, 'reported_at'))      $t->timestamp('reported_at')->nullable();
        });

        $this->patch('quality_history', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'historyable_type')) $t->string('historyable_type')->nullable();
            if (!Schema::hasColumn($table, 'historyable_id'))   $t->unsignedBigInteger('historyable_id')->nullable();
            if (!Schema::hasColumn($table, 'action'))           $t->string('action')->nullable();
            if (!Schema::hasColumn($table, 'field_changed'))    $t->string('field_changed')->nullable();
            if (!Schema::hasColumn($table, 'old_value'))        $t->text('old_value')->nullable();
            if (!Schema::hasColumn($table, 'new_value'))        $t->text('new_value')->nullable();
            if (!Schema::hasColumn($table, 'user_id'))          $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'notes'))            $t->text('notes')->nullable();
        });

        // ── Achats ────────────────────────────────────────────────────────
        $this->patch('achats_suppliers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_person'))   $t->string('contact_person')->nullable();
            if (!Schema::hasColumn($table, 'address'))          $t->text('address')->nullable();
            if (!Schema::hasColumn($table, 'city'))             $t->string('city')->nullable();
            if (!Schema::hasColumn($table, 'tax_number'))       $t->string('tax_number')->nullable();
            if (!Schema::hasColumn($table, 'payment_terms'))    $t->string('payment_terms')->default('net30');
            if (!Schema::hasColumn($table, 'code'))             $t->string('code')->nullable();
        });

        $this->patch('achats_purchase_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'po_number'))        $t->string('po_number')->nullable();
            if (!Schema::hasColumn($table, 'notes'))            $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'delivery_address')) $t->text('delivery_address')->nullable();
            if (!Schema::hasColumn($table, 'payment_terms'))    $t->string('payment_terms')->nullable();
            if (!Schema::hasColumn($table, 'approved_by'))      $t->unsignedBigInteger('approved_by')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))      $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'received_by'))      $t->unsignedBigInteger('received_by')->nullable();
            if (!Schema::hasColumn($table, 'received_at'))      $t->timestamp('received_at')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))       $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'order_date'))       $t->timestamp('order_date')->nullable();
            if (!Schema::hasColumn($table, 'delivery_date'))    $t->timestamp('delivery_date')->nullable();
            if (!Schema::hasColumn($table, 'subtotal'))         $t->decimal('subtotal', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'tax_amount'))       $t->decimal('tax_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'shipping_cost'))    $t->decimal('shipping_cost', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'total'))            $t->decimal('total', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'requested_by'))     $t->unsignedBigInteger('requested_by')->nullable();
        });

        $this->patch('achats_supplier_quotes', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
            if (!Schema::hasColumn($table, 'quote_number'))     $t->string('quote_number')->nullable();
            if (!Schema::hasColumn($table, 'unit_price'))       $t->decimal('unit_price', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'total_price'))      $t->decimal('total_price', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'delivery_days'))    $t->integer('delivery_days')->default(0);
            if (!Schema::hasColumn($table, 'terms'))            $t->text('terms')->nullable();
            if (!Schema::hasColumn($table, 'validity_date'))    $t->timestamp('validity_date')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))       $t->unsignedBigInteger('created_by')->nullable();
            // make rfq_id nullable
        });

        // Make rfq_id nullable in achats_supplier_quotes
        // We cannot alter a NOT NULL column directly in SQLite, but we can add nullable rfq_id via migration
        // The factory sets rfq_id => null, so we need the column to be nullable
        // Fix: change the original table to allow null if not already nullable
        // Since we can't change column constraints in SQLite, we'll work around in factory

        $this->patch('achats_purchase_order_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'unit'))             $t->string('unit', 20)->nullable();
            if (!Schema::hasColumn($table, 'unit_price'))       $t->decimal('unit_price', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'line_total'))       $t->decimal('line_total', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'tax_rate'))         $t->decimal('tax_rate', 5, 2)->default(0);
        });

        $this->patch('achats_rfq_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'unit'))             $t->string('unit', 20)->nullable();
            if (!Schema::hasColumn($table, 'required_date'))    $t->date('required_date')->nullable();
        });

        $this->patch('achats_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'issued_date'))      $t->date('issued_date')->nullable();
            if (!Schema::hasColumn($table, 'deadline_date'))    $t->date('deadline_date')->nullable();
        });

        $this->patch('achats_purchase_receipts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
            if (!Schema::hasColumn($table, 'received_by'))      $t->unsignedBigInteger('received_by')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_location')) $t->string('warehouse_location')->nullable();
            if (!Schema::hasColumn($table, 'receipt_number'))   $t->string('receipt_number')->nullable();
            if (!Schema::hasColumn($table, 'receipt_date'))     $t->date('receipt_date')->nullable();
        });

        $this->patch('validation_approval_workflows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
        });

        // ── Planning ──────────────────────────────────────────────────────
        $this->patch('planning_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
            if (!Schema::hasColumn($table, 'description'))      $t->text('description')->nullable();
        });

        $this->patch('planning_employee_schedules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'scheduled_date'))   $t->date('scheduled_date')->nullable();
            if (!Schema::hasColumn($table, 'notes'))            $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
        });

        $this->patch('planning_shift_coverage_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'schedule_id'))      $t->unsignedBigInteger('schedule_id')->nullable();
            if (!Schema::hasColumn($table, 'shift_id'))         $t->unsignedBigInteger('shift_id')->nullable();
            if (!Schema::hasColumn($table, 'date'))             $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'reason'))           $t->text('reason')->nullable();
        });

        $this->patch('planning_shift_swap_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'requester_id'))         $t->unsignedBigInteger('requester_id')->nullable();
            if (!Schema::hasColumn($table, 'requested_id'))         $t->unsignedBigInteger('requested_id')->nullable();
            if (!Schema::hasColumn($table, 'requested_schedule_id')) $t->unsignedBigInteger('requested_schedule_id')->nullable();
            if (!Schema::hasColumn($table, 'shift_id'))             $t->unsignedBigInteger('shift_id')->nullable();
            if (!Schema::hasColumn($table, 'date'))                 $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'reason'))               $t->text('reason')->nullable();
        });

        $this->patch('planning_shift_coverage_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))      $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'requested_by'))     $t->unsignedBigInteger('requested_by')->nullable();
        });

        $this->patch('planning_schedule_conflicts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'resolution_notes')) $t->text('resolution_notes')->nullable();
            if (!Schema::hasColumn($table, 'resolved_by'))      $t->unsignedBigInteger('resolved_by')->nullable();
            if (!Schema::hasColumn($table, 'resolved_at'))      $t->timestamp('resolved_at')->nullable();
        });

        $this->patch('hr_leave_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
        });

        // ── Email ─────────────────────────────────────────────────────────
        $this->patch('email_automation_flows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_enrolled'))   $t->integer('total_enrolled')->default(0);
            if (!Schema::hasColumn($table, 'total_completed'))  $t->integer('total_completed')->default(0);
        });

        $this->patch('email_campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
            if (!Schema::hasColumn($table, 'is_default'))       $t->boolean('is_default')->default(false);
        });

        $this->patch('email_segments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'filter_conditions')) $t->text('filter_conditions')->nullable();
        });

        $this->patch('email_flow_steps', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))             $t->string('type', 30)->nullable();
            if (!Schema::hasColumn($table, 'config'))           $t->text('config')->nullable();
            if (!Schema::hasColumn($table, 'delay_minutes'))    $t->integer('delay_minutes')->default(0);
            if (!Schema::hasColumn($table, 'flow_id'))          $t->unsignedBigInteger('flow_id')->nullable();
            if (!Schema::hasColumn($table, 'order'))            $t->integer('order')->default(0);
        });

        $this->patch('email_domain_authentications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'dmarc_policy'))     $t->string('dmarc_policy')->nullable();
        });

        $this->patch('email_journeys', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
            if (!Schema::hasColumn($table, 'steps'))            $t->text('steps')->nullable();
        });

        $this->patch('email_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))       $t->softDeletes();
        });

        // ── Documents catalog ─────────────────────────────────────────────
        $this->patch('doc_catalog_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))             $t->string('type', 50)->nullable();
            if (!Schema::hasColumn($table, 'layout'))           $t->string('layout', 50)->nullable();
        });

        $this->patch('doc_catalog_generations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'template_id'))      $t->unsignedBigInteger('template_id')->nullable();
            if (!Schema::hasColumn($table, 'product_ids'))      $t->text('product_ids')->nullable();
            if (!Schema::hasColumn($table, 'formats'))          $t->text('formats')->nullable();
            if (!Schema::hasColumn($table, 'options'))          $t->text('options')->nullable();
        });

        // ── Documents ─────────────────────────────────────────────────────
        // The 'documents' table already has name (NOT NULL) and type (NOT NULL) from the original migration.
        // The Documents module model doesn't fill these; tests create docs without name/type.
        // We can't alter NOT NULL to nullable in SQLite, so we add a DB-level default via raw SQL.
        if (Schema::hasTable('documents')) {
            // Add missing columns from the Documents module perspective
            Schema::table('documents', function (Blueprint $t) {
                if (!Schema::hasColumn('documents', 'title'))        $t->string('title')->nullable();
                if (!Schema::hasColumn('documents', 'storage_path')) $t->string('storage_path')->nullable();
                if (!Schema::hasColumn('documents', 'disk'))         $t->string('disk')->nullable();
                if (!Schema::hasColumn('documents', 'version'))      $t->integer('version')->default(1);
                if (!Schema::hasColumn('documents', 'extension'))    $t->string('extension')->nullable();
                if (!Schema::hasColumn('documents', 'is_locked'))    $t->boolean('is_locked')->default(false);
                if (!Schema::hasColumn('documents', 'metadata'))     $t->text('metadata')->nullable();
                if (!Schema::hasColumn('documents', 'description'))  $t->text('description')->nullable();
                if (!Schema::hasColumn('documents', 'tags'))         $t->text('tags')->nullable();
                if (!Schema::hasColumn('documents', 'created_by'))   $t->unsignedBigInteger('created_by')->nullable();
            });
            // Set a default for name and type so inserts without these fields succeed
            try {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE documents ALTER COLUMN name SET DEFAULT \'untitled\'');
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE documents ALTER COLUMN type SET DEFAULT \'file\'');
            } catch (\Throwable $e) {
                // SQLite doesn't support ALTER COLUMN - use pragma workaround instead
                // Since SQLite can't change constraints, we'll handle in a different wave
            }
        }

        $this->patch('doc_signature_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'document_id'))      $t->unsignedBigInteger('document_id')->nullable();
            if (!Schema::hasColumn($table, 'title'))            $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))       $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'expires_at'))       $t->timestamp('expires_at')->nullable();
        });

        $this->patch('doc_signature_signers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'request_id'))       $t->unsignedBigInteger('request_id')->nullable();
            if (!Schema::hasColumn($table, 'email'))            $t->string('email')->nullable();
            if (!Schema::hasColumn($table, 'signed_at'))        $t->timestamp('signed_at')->nullable();
        });

        $this->patch('doc_content_index', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'document_id'))      $t->unsignedBigInteger('document_id')->nullable();
            if (!Schema::hasColumn($table, 'extracted_text'))   $t->longText('extracted_text')->nullable();
            if (!Schema::hasColumn($table, 'indexed_at'))       $t->timestamp('indexed_at')->nullable();
        });

        $this->patch('doc_approval_instances', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workflow_id'))      $t->unsignedBigInteger('workflow_id')->nullable();
            if (!Schema::hasColumn($table, 'document_id'))      $t->unsignedBigInteger('document_id')->nullable();
            if (!Schema::hasColumn($table, 'current_step'))     $t->integer('current_step')->default(1);
            if (!Schema::hasColumn($table, 'initiated_by'))     $t->unsignedBigInteger('initiated_by')->nullable();
        });

        $this->patch('doc_catalog_generations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'output_paths'))     $t->text('output_paths')->nullable();
            if (!Schema::hasColumn($table, 'generated_at'))     $t->timestamp('generated_at')->nullable();
        });

        $this->patch('doc_signatories', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'signature_request_id')) $t->unsignedBigInteger('signature_request_id')->nullable();
            if (!Schema::hasColumn($table, 'email'))            $t->string('email')->nullable();
            if (!Schema::hasColumn($table, 'token'))            $t->string('token')->nullable();
            if (!Schema::hasColumn($table, 'order'))            $t->integer('order')->default(0);
            if (!Schema::hasColumn($table, 'signed_at'))        $t->timestamp('signed_at')->nullable();
            if (!Schema::hasColumn($table, 'viewed_at'))        $t->timestamp('viewed_at')->nullable();
            if (!Schema::hasColumn($table, 'declined_at'))      $t->timestamp('declined_at')->nullable();
            if (!Schema::hasColumn($table, 'decline_reason'))   $t->text('decline_reason')->nullable();
            if (!Schema::hasColumn($table, 'ip_address'))       $t->string('ip_address')->nullable();
            if (!Schema::hasColumn($table, 'user_agent'))       $t->text('user_agent')->nullable();
        });

        $this->patch('doc_signature_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'completed_at'))     $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))          $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'voided_at'))        $t->timestamp('voided_at')->nullable();
        });

        $this->patch('doc_signature_signers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'order'))            $t->integer('order')->default(0);
            if (!Schema::hasColumn($table, 'name'))             $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'token'))            $t->string('token')->nullable();
        });

        $this->patch('doc_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'content_html'))     $t->longText('content_html')->nullable();
            if (!Schema::hasColumn($table, 'content_json'))     $t->longText('content_json')->nullable();
            if (!Schema::hasColumn($table, 'category'))         $t->string('category', 50)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))        $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'created_by'))       $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'variables'))        $t->text('variables')->nullable();
            if (!Schema::hasColumn($table, 'description'))      $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'uses_count'))       $t->integer('uses_count')->default(0);
        });

        $this->patch('document_shares', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'document_id'))      $t->unsignedBigInteger('document_id')->nullable();
            if (!Schema::hasColumn($table, 'shared_by'))        $t->unsignedBigInteger('shared_by')->nullable();
            if (!Schema::hasColumn($table, 'token'))            $t->string('token')->nullable();
            if (!Schema::hasColumn($table, 'permission'))       $t->string('permission', 20)->default('view');
            if (!Schema::hasColumn($table, 'expires_at'))       $t->timestamp('expires_at')->nullable();
            if (!Schema::hasColumn($table, 'access_count'))     $t->integer('access_count')->default(0);
        });

        $this->patch('document_versions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'document_id'))      $t->unsignedBigInteger('document_id')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))       $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'version_number'))   $t->integer('version_number')->default(1);
            if (!Schema::hasColumn($table, 'storage_path'))     $t->string('storage_path')->nullable();
            if (!Schema::hasColumn($table, 'size'))             $t->unsignedBigInteger('size')->default(0);
            if (!Schema::hasColumn($table, 'change_summary'))   $t->text('change_summary')->nullable();
        });

        $this->patch('quality_inspections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference_type'))   $t->string('reference_type')->nullable();
            if (!Schema::hasColumn($table, 'reference_id'))     $t->unsignedBigInteger('reference_id')->nullable();
        });

        $this->patch('supplier_quality_metrics', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'supplier_id'))          $t->unsignedBigInteger('supplier_id')->nullable();
            if (!Schema::hasColumn($table, 'metric_year'))          $t->integer('metric_year')->nullable();
            if (!Schema::hasColumn($table, 'metric_month'))         $t->integer('metric_month')->nullable();
            if (!Schema::hasColumn($table, 'period_type'))          $t->string('period_type')->nullable();
            if (!Schema::hasColumn($table, 'total_receipts'))       $t->integer('total_receipts')->default(0);
            if (!Schema::hasColumn($table, 'inspected_receipts'))   $t->integer('inspected_receipts')->default(0);
            if (!Schema::hasColumn($table, 'accepted_receipts'))    $t->integer('accepted_receipts')->default(0);
            if (!Schema::hasColumn($table, 'rejected_receipts'))    $t->integer('rejected_receipts')->default(0);
            if (!Schema::hasColumn($table, 'acceptance_rate'))      $t->decimal('acceptance_rate', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'total_defects'))        $t->integer('total_defects')->default(0);
            if (!Schema::hasColumn($table, 'defect_rate'))          $t->decimal('defect_rate', 10, 2)->default(0);
            if (!Schema::hasColumn($table, 'critical_defects'))     $t->integer('critical_defects')->default(0);
            if (!Schema::hasColumn($table, 'major_defects'))        $t->integer('major_defects')->default(0);
            if (!Schema::hasColumn($table, 'minor_defects'))        $t->integer('minor_defects')->default(0);
            if (!Schema::hasColumn($table, 'on_time_deliveries'))   $t->integer('on_time_deliveries')->default(0);
            if (!Schema::hasColumn($table, 'on_time_rate'))         $t->decimal('on_time_rate', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'quality_score'))        $t->decimal('quality_score', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'supplier_status'))      $t->string('supplier_status')->nullable();
            if (!Schema::hasColumn($table, 'performance_notes'))    $t->text('performance_notes')->nullable();
        });

        // ── Quality ───────────────────────────────────────────────────────
        $this->patch('corrective_actions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'action_code'))          $t->string('action_code')->nullable();
            if (!Schema::hasColumn($table, 'description'))          $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'quality_issue_id'))     $t->unsignedBigInteger('quality_issue_id')->nullable();
            if (!Schema::hasColumn($table, 'action_type'))          $t->string('action_type')->nullable();
            if (!Schema::hasColumn($table, 'implementation_plan'))  $t->text('implementation_plan')->nullable();
            if (!Schema::hasColumn($table, 'priority'))             $t->string('priority')->nullable();
            if (!Schema::hasColumn($table, 'assigned_to'))          $t->unsignedBigInteger('assigned_to')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))         $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'verified_at'))          $t->timestamp('verified_at')->nullable();
            if (!Schema::hasColumn($table, 'verified_by'))          $t->unsignedBigInteger('verified_by')->nullable();
            if (!Schema::hasColumn($table, 'completion_notes'))     $t->text('completion_notes')->nullable();
            if (!Schema::hasColumn($table, 'verification_notes'))   $t->text('verification_notes')->nullable();
            if (!Schema::hasColumn($table, 'effectiveness_rating')) $t->integer('effectiveness_rating')->nullable();
            if (!Schema::hasColumn($table, 'requires_audit'))       $t->boolean('requires_audit')->default(false);
            if (!Schema::hasColumn($table, 'audited_at'))           $t->timestamp('audited_at')->nullable();
            if (!Schema::hasColumn($table, 'audit_notes'))          $t->text('audit_notes')->nullable();
            if (!Schema::hasColumn($table, 'due_date'))             $t->date('due_date')->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))           $t->softDeletes();
        });

        $this->patch('supplier_quality_metrics', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at'))           $t->softDeletes();
        });
    }

    public function down(): void {}
};
