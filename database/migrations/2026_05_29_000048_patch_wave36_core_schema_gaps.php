<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, callable $cb): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $cb) {
            $cb($t, $table);
        });
    }

    public function up(): void
    {
        // ── acc_expense_reports: add title column ─────────────────────────
        $this->patch('acc_expense_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title')) $t->string('title')->nullable();
        });

        // ── core_csrf_tokens ──────────────────────────────────────────────
        if (!Schema::hasTable('core_csrf_tokens')) {
            Schema::create('core_csrf_tokens', function (Blueprint $t) {
                $t->string('id', 36)->primary();
                $t->string('user_id')->index();
                $t->string('token_hash', 64)->index();
                $t->string('action', 50)->nullable();
                $t->string('scope', 100)->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->string('user_agent_hash', 64)->nullable();
                $t->timestamp('expires_at');
                $t->timestamp('revoked_at')->nullable();
                $t->timestamp('last_verified_at')->nullable();
                $t->integer('rotation_count')->default(0);
                $t->string('tenant_id')->nullable();
                $t->text('metadata')->nullable();
                $t->timestamps();
            });
        }

        // ── ddos_incidents ────────────────────────────────────────────────
        $this->patch('ddos_incidents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'endpoint'))             $t->string('endpoint')->nullable();
            if (!Schema::hasColumn($table, 'risk_level'))           $t->string('risk_level', 20)->nullable()->default('low');
            if (!Schema::hasColumn($table, 'reason'))               $t->string('reason')->nullable();
            if (!Schema::hasColumn($table, 'attack_signatures'))    $t->text('attack_signatures')->nullable();
            if (!Schema::hasColumn($table, 'request_count'))        $t->integer('request_count')->default(0);
            if (!Schema::hasColumn($table, 'requests_per_second'))  $t->float('requests_per_second')->nullable();
            if (!Schema::hasColumn($table, 'detected_at'))          $t->timestamp('detected_at')->nullable();
            if (!Schema::hasColumn($table, 'blocked_until'))        $t->timestamp('blocked_until')->nullable();
            if (!Schema::hasColumn($table, 'auto_unblock_at'))      $t->timestamp('auto_unblock_at')->nullable();
            if (!Schema::hasColumn($table, 'auto_blocked'))         $t->boolean('auto_blocked')->default(false);
            if (!Schema::hasColumn($table, 'attack_signature'))     $t->string('attack_signature')->nullable();
            if (!Schema::hasColumn($table, 'metrics'))              $t->text('metrics')->nullable();
            if (!Schema::hasColumn($table, 'tenant_id'))            $t->string('tenant_id')->nullable();
            if (!Schema::hasColumn($table, 'created_at'))           $t->timestamp('created_at')->nullable();
        });

        // ── core_approval_workflows ───────────────────────────────────────
        $this->patch('core_approval_workflows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))           $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))    $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'module'))         $t->string('module', 60)->nullable();
            if (!Schema::hasColumn($table, 'resource_type'))  $t->string('resource_type', 60)->nullable();
            if (!Schema::hasColumn($table, 'steps'))          $t->text('steps')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))      $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'allow_parallel')) $t->boolean('allow_parallel')->default(false);
            if (!Schema::hasColumn($table, 'created_by'))     $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── core_approval_instances ───────────────────────────────────────
        $this->patch('core_approval_instances', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workflow_id'))    $t->unsignedBigInteger('workflow_id')->nullable();
            if (!Schema::hasColumn($table, 'subject_type'))   $t->string('subject_type', 100)->nullable();
            if (!Schema::hasColumn($table, 'subject_id'))     $t->unsignedBigInteger('subject_id')->nullable();
            if (!Schema::hasColumn($table, 'current_step'))   $t->integer('current_step')->default(1);
            if (!Schema::hasColumn($table, 'initiated_by'))   $t->unsignedBigInteger('initiated_by')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))   $t->timestamp('completed_at')->nullable();
        });

        // ── core_approval_decisions ───────────────────────────────────────
        $this->patch('core_approval_decisions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'instance_id'))    $t->unsignedBigInteger('instance_id')->nullable();
            if (!Schema::hasColumn($table, 'step'))           $t->integer('step')->nullable();
            if (!Schema::hasColumn($table, 'step_order'))     $t->integer('step_order')->nullable();
            if (!Schema::hasColumn($table, 'decision'))       $t->string('decision', 20)->nullable();
            if (!Schema::hasColumn($table, 'comment'))        $t->text('comment')->nullable();
            if (!Schema::hasColumn($table, 'decided_by'))     $t->unsignedBigInteger('decided_by')->nullable();
            if (!Schema::hasColumn($table, 'approver_id'))    $t->unsignedBigInteger('approver_id')->nullable();
            if (!Schema::hasColumn($table, 'decided_at'))     $t->timestamp('decided_at')->nullable();
        });

        // ── core_workflow_states ──────────────────────────────────────────
        $this->patch('core_workflow_states', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'subject_type'))              $t->string('subject_type', 150)->nullable();
            if (!Schema::hasColumn($table, 'subject_id'))                $t->unsignedBigInteger('subject_id')->nullable();
            if (!Schema::hasColumn($table, 'workflow_definition_id'))    $t->unsignedBigInteger('workflow_definition_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))                      $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'is_initial'))                $t->boolean('is_initial')->default(false);
            if (!Schema::hasColumn($table, 'is_final'))                  $t->boolean('is_final')->default(false);
            if (!Schema::hasColumn($table, 'current_step'))              $t->string('current_step', 50)->nullable();
            if (!Schema::hasColumn($table, 'started_at'))                $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))              $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'metadata'))                  $t->text('metadata')->nullable();
        });

        // ── core_workflow_definitions ─────────────────────────────────────
        $this->patch('core_workflow_definitions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))           $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'module'))         $t->string('module', 60)->nullable();
            if (!Schema::hasColumn($table, 'resource_type'))  $t->string('resource_type', 60)->nullable();
            if (!Schema::hasColumn($table, 'transitions'))    $t->text('transitions')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))      $t->boolean('is_active')->default(true);
        });

        // ── core_gdpr_consents: make user_id nullable, drop unique constraint
        if (Schema::hasTable('core_gdpr_consents')) {
            Schema::table('core_gdpr_consents', function (Blueprint $t) {
                if (Schema::hasColumn('core_gdpr_consents', 'user_id')) {
                    $t->unsignedBigInteger('user_id')->nullable()->change();
                }
                // Drop unique constraint if present; must drop FK first if it exists,
                // as MySQL 1553 prevents dropping an index used by a FK.
                if (Schema::hasIndex('core_gdpr_consents', 'core_gdpr_consents_user_id_consent_type_unique')) {
                    try {
                        $t->dropForeign(['user_id']);
                    } catch (\Exception $e) {
                        // FK may not exist
                    }
                    try {
                        $t->dropUnique('core_gdpr_consents_user_id_consent_type_unique');
                        // Restore plain index so queries remain fast
                        $t->index(['user_id', 'consent_type'], 'core_gdpr_consents_user_id_consent_type_idx');
                    } catch (\Exception $e) {
                        // Unique may have already been dropped
                    }
                }
            });
        }
    }

    public function down(): void {}
};
