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
        // ── documents ─────────────────────────────────────────────────────
        $this->patch('documents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'disk'))          $t->string('disk', 30)->default('local');
            if (!Schema::hasColumn($table, 'uploaded_by'))   $t->unsignedBigInteger('uploaded_by')->nullable();
            if (!Schema::hasColumn($table, 'version'))       $t->string('version', 10)->default('1.0');
            if (!Schema::hasColumn($table, 'is_public'))     $t->boolean('is_public')->default(false);
            if (!Schema::hasColumn($table, 'tags'))          $t->text('tags')->nullable();
            if (!Schema::hasColumn($table, 'is_locked'))        $t->boolean('is_locked')->default(false);
            if (!Schema::hasColumn($table, 'status'))           $t->string('status', 20)->default('active');
            if (!Schema::hasColumn($table, 'requires_approval')) $t->boolean('requires_approval')->default(false);
        });

        // ── wfd_definitions (Workflow) ────────────────────────────────────
        $this->patch('wfd_definitions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'module'))        $t->string('module', 50)->nullable();
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'trigger_event')) $t->string('trigger_event', 100)->nullable();
            if (!Schema::hasColumn($table, 'conditions'))    $t->text('conditions')->nullable();
            if (!Schema::hasColumn($table, 'actions'))       $t->text('actions')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))     $t->boolean('is_active')->default(true);
        });

        // ── wfd_actions (Workflow) ────────────────────────────────────────
        $this->patch('wfd_actions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workflow_id'))   $t->unsignedBigInteger('workflow_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'type'))          $t->string('type', 50)->nullable();
            if (!Schema::hasColumn($table, 'config'))        $t->text('config')->nullable();
            if (!Schema::hasColumn($table, 'order'))         $t->integer('order')->default(0);
            if (!Schema::hasColumn($table, 'action_type'))   $t->string('action_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'action_config')) $t->text('action_config')->nullable();
        });

        // ── wfd_executions (Workflow) ─────────────────────────────────────
        $this->patch('wfd_executions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workflow_id'))   $t->unsignedBigInteger('workflow_id')->nullable();
            if (!Schema::hasColumn($table, 'status'))        $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'input'))         $t->text('input')->nullable();
            if (!Schema::hasColumn($table, 'output'))        $t->text('output')->nullable();
            if (!Schema::hasColumn($table, 'started_at'))    $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))  $t->timestamp('completed_at')->nullable();
        });

        // ── achats_rfqs ───────────────────────────────────────────────────
        $this->patch('achats_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'rfq_number'))    $t->string('rfq_number')->nullable();
            if (!Schema::hasColumn($table, 'title'))         $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'supplier_id'))   $t->unsignedBigInteger('supplier_id')->nullable();
            if (!Schema::hasColumn($table, 'currency'))      $t->string('currency', 10)->default('XOF');
        });

        // ── marketing_contacts (MarketingAutomation) ──────────────────────
        $this->patch('marketing_contacts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'lead_score'))    $t->integer('lead_score')->default(0);
            if (!Schema::hasColumn($table, 'lifecycle_stage')) $t->string('lifecycle_stage', 30)->default('subscriber');
            if (!Schema::hasColumn($table, 'subscribed'))    $t->boolean('subscribed')->default(true);
            if (!Schema::hasColumn($table, 'unsubscribed_at')) $t->timestamp('unsubscribed_at')->nullable();
            if (!Schema::hasColumn($table, 'source'))          $t->string('source', 50)->nullable();
            if (!Schema::hasColumn($table, 'opt_in_consent'))  $t->boolean('opt_in_consent')->default(false);
            if (!Schema::hasColumn($table, 'is_suppressed'))   $t->boolean('is_suppressed')->default(false);
        });

        // ── campaigns (MarketingAutomation) ───────────────────────────────
        $this->patch('campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'type'))          $t->string('type', 30)->default('email');
            if (!Schema::hasColumn($table, 'campaign_type')) $t->string('campaign_type', 30)->default('email');
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'subject'))       $t->string('subject')->nullable();
            if (!Schema::hasColumn($table, 'body'))          $t->text('body')->nullable();
            if (!Schema::hasColumn($table, 'segment_id'))    $t->unsignedBigInteger('segment_id')->nullable();
            if (!Schema::hasColumn($table, 'scheduled_at'))  $t->timestamp('scheduled_at')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))       $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))     $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'budget'))        $t->decimal('budget', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'created_by'))    $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── segments (MarketingAutomation) ────────────────────────────────
        $this->patch('segments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'conditions'))    $t->text('conditions')->nullable();
            if (!Schema::hasColumn($table, 'criteria'))       $t->text('criteria')->nullable();
            if (!Schema::hasColumn($table, 'count'))         $t->integer('count')->default(0);
            if (!Schema::hasColumn($table, 'contact_count')) $t->integer('contact_count')->default(0);
        });
    }

    public function down(): void {}
};
