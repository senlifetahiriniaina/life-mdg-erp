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
        // ── CRM: crm_sequence_steps (email-related columns) ───────────────
        $this->patch('crm_sequence_steps', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'from_name'))          $t->string('from_name', 100)->nullable();
            if (!Schema::hasColumn($table, 'from_email'))         $t->string('from_email', 150)->nullable();
            if (!Schema::hasColumn($table, 'subject'))            $t->string('subject', 300)->nullable();
            if (!Schema::hasColumn($table, 'body'))               $t->text('body')->nullable();
            if (!Schema::hasColumn($table, 'order'))              $t->integer('order')->default(0);
        });

        // ── CRM: crm_email_sequence_enrollments ───────────────────────────
        $this->patch('crm_email_sequence_enrollments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sequence_id'))        $t->unsignedBigInteger('sequence_id')->nullable();
            if (!Schema::hasColumn($table, 'contact_id'))         $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'current_step'))       $t->integer('current_step')->default(0);
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('active');
            if (!Schema::hasColumn($table, 'enrolled_at'))        $t->timestamp('enrolled_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))       $t->timestamp('completed_at')->nullable();
        });

        // ── CRM: crm_quote_lines ──────────────────────────────────────────
        $this->patch('crm_quote_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quote_id'))           $t->unsignedBigInteger('quote_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'product_bundle_id'))  $t->unsignedBigInteger('product_bundle_id')->nullable();
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))           $t->decimal('quantity', 15, 4)->default(1);
            if (!Schema::hasColumn($table, 'unit_price'))         $t->decimal('unit_price', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'discount_pct'))       $t->decimal('discount_pct', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'tax_rate'))           $t->decimal('tax_rate', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'line_total'))         $t->decimal('line_total', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'sort_order'))         $t->integer('sort_order')->default(0);
        });

        // ── CRM: crm_web_form_submissions (more columns) ──────────────────
        $this->patch('crm_web_form_submissions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_agent'))         $t->string('user_agent', 500)->nullable();
        });

        // ── CRM: crm_product_bundles ──────────────────────────────────────
        $this->patch('crm_product_bundles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'items'))              $t->text('items')->nullable();
            if (!Schema::hasColumn($table, 'total_price'))        $t->decimal('total_price', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'discount_pct'))       $t->decimal('discount_pct', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── CRM: crm_opportunity_history ──────────────────────────────────
        $this->patch('crm_opportunity_history', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'opportunity_id'))     $t->unsignedBigInteger('opportunity_id')->nullable();
            if (!Schema::hasColumn($table, 'field_name'))         $t->string('field_name', 100)->nullable();
            if (!Schema::hasColumn($table, 'old_value'))          $t->text('old_value')->nullable();
            if (!Schema::hasColumn($table, 'new_value'))          $t->text('new_value')->nullable();
            if (!Schema::hasColumn($table, 'changed_by'))         $t->unsignedBigInteger('changed_by')->nullable();
        });
    }

    public function down(): void {}
};
