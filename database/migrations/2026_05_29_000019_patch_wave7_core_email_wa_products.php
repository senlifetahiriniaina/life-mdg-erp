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
        // ── documents (more columns) ──────────────────────────────────────
        $this->patch('documents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'storage_path'))  $t->text('storage_path')->nullable();
            if (!Schema::hasColumn($table, 'extension'))     $t->string('extension', 20)->nullable();
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
        });

        // ── products ──────────────────────────────────────────────────────
        $this->patch('products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_code'))  $t->string('product_code')->nullable();
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'price'))         $t->decimal('price', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))     $t->boolean('is_active')->default(true);
        });

        // ── marketing_contacts ────────────────────────────────────────────
        $this->patch('marketing_contacts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'email'))       $t->string('email')->nullable();
            if (!Schema::hasColumn($table, 'first_name'))  $t->string('first_name')->nullable();
            if (!Schema::hasColumn($table, 'last_name'))   $t->string('last_name')->nullable();
            if (!Schema::hasColumn($table, 'phone'))       $t->string('phone')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
        });

        // ── email_automation_flows ────────────────────────────────────────
        $this->patch('email_automation_flows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'trigger'))      $t->string('trigger')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });

        // ── email_domain_authentications ──────────────────────────────────
        $this->patch('email_domain_authentications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'domain'))       $t->string('domain')->nullable();
            if (!Schema::hasColumn($table, 'dkim_key'))     $t->text('dkim_key')->nullable();
            if (!Schema::hasColumn($table, 'is_verified'))  $t->boolean('is_verified')->default(false);
            if (!Schema::hasColumn($table, 'verified_at')) $t->timestamp('verified_at')->nullable();
        });

        // ── workflows ─────────────────────────────────────────────────────
        $this->patch('workflows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_type'))  $t->string('trigger_type', 30)->nullable();
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))     $t->boolean('is_active')->default(true);
        });

        // ── wa_broadcast_campaigns ────────────────────────────────────────
        $this->patch('wa_broadcast_campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))     $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'name'))       $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'message'))    $t->text('message')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))    $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'sent_count')) $t->integer('sent_count')->default(0);
        });

        // ── wa_chatbot_intents ────────────────────────────────────────────
        $this->patch('wa_chatbot_intents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'patterns'))      $t->text('patterns')->nullable();
            if (!Schema::hasColumn($table, 'response'))      $t->text('response')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))     $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'confidence'))    $t->decimal('confidence', 5, 2)->default(0.8);
        });

        // ── core_custom_fields ────────────────────────────────────────────
        $this->patch('core_custom_fields', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'entity_type'))  $t->string('entity_type')->nullable();
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 20)->default('text');
            if (!Schema::hasColumn($table, 'label'))        $t->string('label')->nullable();
            if (!Schema::hasColumn($table, 'is_required'))  $t->boolean('is_required')->default(false);
            if (!Schema::hasColumn($table, 'options'))      $t->text('options')->nullable();
        });

        // ── core_approval_workflows ───────────────────────────────────────
        $this->patch('core_approval_workflows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'steps'))        $t->text('steps')->nullable();
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'entity_type'))  $t->string('entity_type')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });

        // ── core_gdpr_consents (more columns) ─────────────────────────────
        $this->patch('core_gdpr_consents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'granted_at'))   $t->timestamp('granted_at')->nullable();
            if (!Schema::hasColumn($table, 'revoked_at'))   $t->timestamp('revoked_at')->nullable();
            if (!Schema::hasColumn($table, 'ip_address'))   $t->string('ip_address')->nullable();
        });

        // ── core_import_rows ──────────────────────────────────────────────
        $this->patch('core_import_rows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'import_job_id'))   $t->unsignedBigInteger('import_job_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'row_index'))       $t->integer('row_index')->default(0);
            if (!Schema::hasColumn($table, 'raw_data'))        $t->text('raw_data')->nullable();
            if (!Schema::hasColumn($table, 'mapped_data'))     $t->text('mapped_data')->nullable();
            if (!Schema::hasColumn($table, 'errors'))          $t->text('errors')->nullable();
            if (!Schema::hasColumn($table, 'error_message'))   $t->text('error_message')->nullable();
            if (!Schema::hasColumn($table, 'created_record_id')) $t->unsignedBigInteger('created_record_id')->nullable();
        });

        // ── core_import_jobs ──────────────────────────────────────────────
        $this->patch('core_import_jobs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_id'))       $t->unsignedBigInteger('user_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'filename'))      $t->string('filename')->nullable();
            if (!Schema::hasColumn($table, 'file_path'))     $t->text('file_path')->nullable();
            if (!Schema::hasColumn($table, 'file_type'))     $t->string('file_type')->nullable();
            if (!Schema::hasColumn($table, 'entity_type'))   $t->string('entity_type')->nullable();
            if (!Schema::hasColumn($table, 'target_entity')) $t->string('target_entity')->nullable();
            if (!Schema::hasColumn($table, 'total_rows'))    $t->integer('total_rows')->default(0);
            if (!Schema::hasColumn($table, 'processed'))     $t->integer('processed')->default(0);
            if (!Schema::hasColumn($table, 'failed'))        $t->integer('failed')->default(0);
            if (!Schema::hasColumn($table, 'imported'))      $t->integer('imported')->default(0);
            if (!Schema::hasColumn($table, 'errors'))        $t->text('errors')->nullable();
            if (!Schema::hasColumn($table, 'column_mapping')) $t->text('column_mapping')->nullable();
            if (!Schema::hasColumn($table, 'is_complete'))   $t->boolean('is_complete')->default(false);
            if (!Schema::hasColumn($table, 'started_at'))    $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))  $t->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void {}
};
