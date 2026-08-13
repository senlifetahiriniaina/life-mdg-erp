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
        // ── hd_sla_policies ───────────────────────────────────────────────
        $this->patch('hd_sla_policies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))              $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'priority'))                 $t->string('priority', 20)->default('medium');
            if (!Schema::hasColumn($table, 'response_time_minutes'))    $t->integer('response_time_minutes')->default(60);
            if (!Schema::hasColumn($table, 'resolution_time_minutes'))  $t->integer('resolution_time_minutes')->default(480);
            if (!Schema::hasColumn($table, 'business_hours_only'))      $t->boolean('business_hours_only')->default(false);
            if (!Schema::hasColumn($table, 'escalation_enabled'))       $t->boolean('escalation_enabled')->default(true);
            if (!Schema::hasColumn($table, 'escalation_after_minutes')) $t->integer('escalation_after_minutes')->nullable();
        });

        // ── hd_helpdesk_sla_policies ──────────────────────────────────────
        $this->patch('hd_helpdesk_sla_policies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))                  $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'priority'))              $t->string('priority', 20)->default('medium');
            if (!Schema::hasColumn($table, 'first_response_hours'))  $t->integer('first_response_hours')->default(4);
            if (!Schema::hasColumn($table, 'resolution_hours'))      $t->integer('resolution_hours')->default(24);
            if (!Schema::hasColumn($table, 'business_hours_only'))   $t->boolean('business_hours_only')->default(false);
            if (!Schema::hasColumn($table, 'is_default'))            $t->boolean('is_default')->default(false);
        });

        // ── hd_kb_articles ────────────────────────────────────────────────
        $this->patch('hd_kb_articles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'category_id'))  $t->unsignedBigInteger('category_id')->nullable();
            if (!Schema::hasColumn($table, 'title'))        $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'content'))      $t->text('content')->nullable();
            if (!Schema::hasColumn($table, 'status'))       $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'created_by'))   $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'published_at')) $t->timestamp('published_at')->nullable();
            if (!Schema::hasColumn($table, 'slug'))         $t->string('slug')->nullable();
            if (!Schema::hasColumn($table, 'views_count'))  $t->integer('views_count')->default(0);
            if (!Schema::hasColumn($table, 'helpful_count')) $t->integer('helpful_count')->default(0);
        });

        // ── hd_chat_sessions ──────────────────────────────────────────────
        $this->patch('hd_chat_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'visitor_id'))    $t->string('visitor_id')->nullable();
            if (!Schema::hasColumn($table, 'visitor_name'))  $t->string('visitor_name')->nullable();
            if (!Schema::hasColumn($table, 'visitor_email')) $t->string('visitor_email')->nullable();
            if (!Schema::hasColumn($table, 'started_at'))    $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'channel'))       $t->string('channel', 20)->default('web');
            if (!Schema::hasColumn($table, 'metadata'))      $t->text('metadata')->nullable();
            if (!Schema::hasColumn($table, 'agent_id'))      $t->unsignedBigInteger('agent_id')->nullable();
            if (!Schema::hasColumn($table, 'ended_at'))      $t->timestamp('ended_at')->nullable();
        });

        // ── helpdesk_forum_posts ──────────────────────────────────────────
        $this->patch('helpdesk_forum_posts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))              $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'content'))            $t->text('content')->nullable();
            if (!Schema::hasColumn($table, 'author_id'))          $t->unsignedBigInteger('author_id')->nullable();
            if (!Schema::hasColumn($table, 'category'))           $t->string('category')->nullable();
            if (!Schema::hasColumn($table, 'votes'))              $t->integer('votes')->default(0);
            if (!Schema::hasColumn($table, 'views'))              $t->integer('views')->default(0);
            if (!Schema::hasColumn($table, 'accepted_answer_id')) $t->unsignedBigInteger('accepted_answer_id')->nullable();
        });

        // ── helpdesk_csat_campaigns ───────────────────────────────────────
        $this->patch('helpdesk_csat_campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'trigger'))       $t->string('trigger')->nullable();
            if (!Schema::hasColumn($table, 'delay_hours'))   $t->integer('delay_hours')->default(24);
            if (!Schema::hasColumn($table, 'question_text')) $t->text('question_text')->nullable();
            if (!Schema::hasColumn($table, 'active'))        $t->boolean('active')->default(true);
        });

        // ── helpdesk_bot_deflections ──────────────────────────────────────
        $this->patch('helpdesk_bot_deflections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'question'))           $t->text('question')->nullable();
            if (!Schema::hasColumn($table, 'matched_article_id')) $t->unsignedBigInteger('matched_article_id')->nullable();
            if (!Schema::hasColumn($table, 'deflected'))          $t->boolean('deflected')->default(false);
            if (!Schema::hasColumn($table, 'ticket_created'))     $t->boolean('ticket_created')->default(false);
            if (!Schema::hasColumn($table, 'session_id'))         $t->string('session_id')->nullable();
        });

        // ── crm_email_sequences ───────────────────────────────────────────
        $this->patch('crm_email_sequences', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger'))       $t->string('trigger')->nullable();
            if (!Schema::hasColumn($table, 'status'))        $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'trigger_type'))  $t->string('trigger_type')->nullable();
        });

        // ── crm_win_loss_records ──────────────────────────────────────────
        $this->patch('crm_win_loss_records', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sales_cycle_days')) $t->integer('sales_cycle_days')->default(0);
            if (!Schema::hasColumn($table, 'outcome'))          $t->string('outcome', 20)->default('won');
            if (!Schema::hasColumn($table, 'reason'))           $t->text('reason')->nullable();
            if (!Schema::hasColumn($table, 'competitor'))       $t->string('competitor')->nullable();
            if (!Schema::hasColumn($table, 'deal_value'))       $t->decimal('deal_value', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'recorded_by'))      $t->unsignedBigInteger('recorded_by')->nullable();
            if (!Schema::hasColumn($table, 'recorded_at'))      $t->timestamp('recorded_at')->nullable();
        });

        // ── crm_pipeline_snapshots ────────────────────────────────────────
        $this->patch('crm_pipeline_snapshots', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'pipeline_id'))    $t->unsignedBigInteger('pipeline_id')->nullable();
            if (!Schema::hasColumn($table, 'snapshot_date'))  $t->date('snapshot_date')->nullable();
            if (!Schema::hasColumn($table, 'total_value'))    $t->decimal('total_value', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'deal_count'))     $t->integer('deal_count')->default(0);
            if (!Schema::hasColumn($table, 'avg_deal_size'))  $t->decimal('avg_deal_size', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'stage_data'))     $t->text('stage_data')->nullable();
        });

        // ── crm_opportunity_history ───────────────────────────────────────
        $this->patch('crm_opportunity_history', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'opportunity_id')) $t->unsignedBigInteger('opportunity_id')->nullable();
            if (!Schema::hasColumn($table, 'field'))          $t->string('field')->nullable();
            if (!Schema::hasColumn($table, 'old_value'))      $t->text('old_value')->nullable();
            if (!Schema::hasColumn($table, 'new_value'))      $t->text('new_value')->nullable();
            if (!Schema::hasColumn($table, 'changed_by'))     $t->unsignedBigInteger('changed_by')->nullable();
            if (!Schema::hasColumn($table, 'changed_at'))     $t->timestamp('changed_at')->nullable();
        });

        // ── crm_forecasts ─────────────────────────────────────────────────
        $this->patch('crm_forecasts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'forecast_amount')) $t->decimal('forecast_amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'period'))          $t->string('period', 20)->nullable();
            if (!Schema::hasColumn($table, 'user_id'))         $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'commit_amount'))   $t->decimal('commit_amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'best_case'))       $t->decimal('best_case', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'pipeline_total'))  $t->decimal('pipeline_total', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'ai_prediction'))   $t->decimal('ai_prediction', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'confidence_pct'))  $t->integer('confidence_pct')->default(0);
            if (!Schema::hasColumn($table, 'generated_at'))    $t->timestamp('generated_at')->nullable();
        });
    }

    public function down(): void {}
};
