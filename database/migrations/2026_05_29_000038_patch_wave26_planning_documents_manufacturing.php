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
        // ── Planning ──────────────────────────────────────────────────────
        $this->patch('planning_shift_swap_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'target_employee_id'))    $t->unsignedBigInteger('target_employee_id')->nullable();
            if (!Schema::hasColumn($table, 'target_schedule_id'))    $t->unsignedBigInteger('target_schedule_id')->nullable();
            if (!Schema::hasColumn($table, 'requester_id'))          $t->unsignedBigInteger('requester_id')->nullable();
            if (!Schema::hasColumn($table, 'requested_schedule_id')) $t->unsignedBigInteger('requested_schedule_id')->nullable();
            if (!Schema::hasColumn($table, 'reason'))                $t->text('reason')->nullable();
            if (!Schema::hasColumn($table, 'status'))                $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'reviewed_by'))           $t->unsignedBigInteger('reviewed_by')->nullable();
            if (!Schema::hasColumn($table, 'reviewed_at'))           $t->timestamp('reviewed_at')->nullable();
        });

        $this->patch('planning_shift_coverage_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'assigned_to'))           $t->unsignedBigInteger('assigned_to')->nullable();
            if (!Schema::hasColumn($table, 'shift_id'))              $t->unsignedBigInteger('shift_id')->nullable();
            if (!Schema::hasColumn($table, 'requester_id'))          $t->unsignedBigInteger('requester_id')->nullable();
            if (!Schema::hasColumn($table, 'date'))                  $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'reason'))                $t->text('reason')->nullable();
            if (!Schema::hasColumn($table, 'status'))                $t->string('status', 20)->default('open');
        });

        // ── Documents ─────────────────────────────────────────────────────
        $this->patch('doc_signatures', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'signer_id'))             $t->unsignedBigInteger('signer_id')->nullable();
            if (!Schema::hasColumn($table, 'signature_data'))        $t->text('signature_data')->nullable();
            if (!Schema::hasColumn($table, 'position_page'))         $t->integer('position_page')->default(1);
            if (!Schema::hasColumn($table, 'position_x'))            $t->decimal('position_x', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'position_y'))            $t->decimal('position_y', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'position_width'))        $t->decimal('position_width', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'position_height'))       $t->decimal('position_height', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'signed_at'))             $t->timestamp('signed_at')->nullable();
        });

        $this->patch('doc_signature_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'message'))               $t->text('message')->nullable();
            if (!Schema::hasColumn($table, 'title'))                 $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'document_id'))           $t->unsignedBigInteger('document_id')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))            $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'status'))                $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'expires_at'))            $t->timestamp('expires_at')->nullable();
        });

        $this->patch('document_workspaces', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'visibility'))            $t->string('visibility', 20)->default('private');
        });

        $this->patch('doc_approval_decisions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'instance_id'))           $t->unsignedBigInteger('instance_id')->nullable();
            if (!Schema::hasColumn($table, 'step_number'))           $t->integer('step_number')->default(1);
            if (!Schema::hasColumn($table, 'approver_id'))           $t->unsignedBigInteger('approver_id')->nullable();
            if (!Schema::hasColumn($table, 'decision'))              $t->string('decision', 20)->default('pending');
            if (!Schema::hasColumn($table, 'comment'))               $t->text('comment')->nullable();
            if (!Schema::hasColumn($table, 'decided_at'))            $t->timestamp('decided_at')->nullable();
        });

        // ── Manufacturing ─────────────────────────────────────────────────
        $this->patch('mfg_capacity_constraints', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_holiday'))            $t->boolean('is_holiday')->default(false);
        });

        $this->patch('mfg_capacity_allocations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'scheduled_date'))        $t->date('scheduled_date')->nullable();
            if (!Schema::hasColumn($table, 'allocated_hours'))       $t->decimal('allocated_hours', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'sequence'))              $t->integer('sequence')->default(0);
        });

        // ── WhatsApp ──────────────────────────────────────────────────────
        $this->patch('wa_rate_limits', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'phone'))                 $t->string('phone')->nullable();
            if (!Schema::hasColumn($table, 'window_start'))          $t->timestamp('window_start')->nullable();
            if (!Schema::hasColumn($table, 'message_count'))         $t->integer('message_count')->default(0);
            if (!Schema::hasColumn($table, 'limit'))                 $t->integer('limit')->default(1000);
            if (!Schema::hasColumn($table, 'reset_at'))              $t->timestamp('reset_at')->nullable();
        });

        $this->patch('wa_message_logs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'message_id'))            $t->string('message_id')->nullable();
            if (!Schema::hasColumn($table, 'phone'))                 $t->string('phone')->nullable();
            if (!Schema::hasColumn($table, 'direction'))             $t->string('direction', 10)->default('outbound');
            if (!Schema::hasColumn($table, 'type'))                  $t->string('type', 30)->default('text');
            if (!Schema::hasColumn($table, 'content'))               $t->text('content')->nullable();
            if (!Schema::hasColumn($table, 'status'))                $t->string('status', 20)->default('sent');
            if (!Schema::hasColumn($table, 'error'))                 $t->text('error')->nullable();
        });

        $this->patch('wa_product_catalogs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'catalog_id'))            $t->string('catalog_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))                  $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'synced_at'))             $t->timestamp('synced_at')->nullable();
            if (!Schema::hasColumn($table, 'product_count'))         $t->integer('product_count')->default(0);
        });

        // ── Accounting ────────────────────────────────────────────────────
        $this->patch('acc_gl_accounts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'parent_id'))             $t->unsignedBigInteger('parent_id')->nullable();
            if (!Schema::hasColumn($table, 'code'))                  $t->string('code', 20)->nullable();
            if (!Schema::hasColumn($table, 'name'))                  $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'normal_balance'))        $t->string('normal_balance', 6)->default('debit');
            if (!Schema::hasColumn($table, 'is_active'))             $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'ohada_class'))           $t->string('ohada_class', 5)->nullable();
        });

        $this->patch('acc_tax_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))                  $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'rate'))                  $t->decimal('rate', 5, 4)->default(0);
            if (!Schema::hasColumn($table, 'type'))                  $t->string('type', 20)->default('percentage');
            if (!Schema::hasColumn($table, 'applies_to'))            $t->string('applies_to', 30)->default('all');
            if (!Schema::hasColumn($table, 'is_active'))             $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'country'))               $t->string('country', 5)->default('SN');
        });

        $this->patch('acc_payment_terms', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))                  $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'days'))                  $t->integer('days')->default(30);
            if (!Schema::hasColumn($table, 'discount_days'))         $t->integer('discount_days')->nullable();
            if (!Schema::hasColumn($table, 'discount_pct'))          $t->decimal('discount_pct', 5, 4)->default(0);
            if (!Schema::hasColumn($table, 'is_active'))             $t->boolean('is_active')->default(true);
        });

        $this->patch('acc_expense_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'employee_id'))           $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'title'))                 $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'total_amount'))          $t->decimal('total_amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'currency'))              $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'submitted_at'))          $t->timestamp('submitted_at')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))           $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'approved_by'))           $t->unsignedBigInteger('approved_by')->nullable();
        });
    }

    public function down(): void {}
};
