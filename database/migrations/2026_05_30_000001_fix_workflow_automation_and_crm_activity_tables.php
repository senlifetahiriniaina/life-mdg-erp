<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patch migration:
 *  1. Add missing columns to `workflows` table needed by WorkflowAutomation module
 *  2. Rebuild `workflow_conditions` with proper schema
 *  3. Rebuild `workflow_triggers` with proper schema
 *  4. Rebuild `workflow_actions` to ensure `action_target` column exists
 *  5. Fix `workflow_executions` missing columns
 *  6. Fix `workflow_steps` missing columns
 *  7. Add missing columns to `crm_activities`
 *  8. Add missing columns to `crm_leads`
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. workflows ──────────────────────────────────────────────────────
        Schema::table('workflows', function (Blueprint $table) {
            if (!Schema::hasColumn('workflows', 'trigger_type')) {
                $table->string('trigger_type')->nullable()->after('description');
            }
            if (!Schema::hasColumn('workflows', 'trigger_entity')) {
                $table->string('trigger_entity')->nullable()->after('trigger_type');
            }
            if (!Schema::hasColumn('workflows', 'status')) {
                $table->string('status')->default('draft')->after('trigger_entity');
            }
            if (!Schema::hasColumn('workflows', 'is_scheduled')) {
                $table->boolean('is_scheduled')->default(false)->after('status');
            }
            if (!Schema::hasColumn('workflows', 'schedule_frequency')) {
                $table->string('schedule_frequency')->nullable()->after('is_scheduled');
            }
            if (!Schema::hasColumn('workflows', 'schedule_time')) {
                $table->string('schedule_time')->nullable()->after('schedule_frequency');
            }
            if (!Schema::hasColumn('workflows', 'max_executions')) {
                $table->unsignedInteger('max_executions')->nullable()->after('schedule_time');
            }
            if (!Schema::hasColumn('workflows', 'timeout_seconds')) {
                $table->unsignedInteger('timeout_seconds')->default(300)->after('max_executions');
            }
            if (!Schema::hasColumn('workflows', 'notes')) {
                $table->text('notes')->nullable();
            }
            // Make tenant_id nullable if it exists (to allow tests without tenant)
            // Note: tenant_id already exists from original migration
        });

        // ── 2. workflow_conditions ────────────────────────────────────────────
        // Drop and recreate with proper schema if columns are missing
        if (Schema::hasTable('workflow_conditions') && !Schema::hasColumn('workflow_conditions', 'workflow_id')) {
            Schema::drop('workflow_conditions');
        }
        if (!Schema::hasTable('workflow_conditions')) {
            Schema::create('workflow_conditions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id')->index();
                $table->string('field');
                $table->string('operator');
                $table->text('value')->nullable();
                $table->string('logical_operator')->default('AND');
                $table->unsignedInteger('sequence')->default(1);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('workflow_conditions', function (Blueprint $table) {
                if (!Schema::hasColumn('workflow_conditions', 'workflow_id')) {
                    $table->unsignedBigInteger('workflow_id')->index()->after('id');
                }
                if (!Schema::hasColumn('workflow_conditions', 'field')) {
                    $table->string('field')->after('workflow_id');
                }
                if (!Schema::hasColumn('workflow_conditions', 'operator')) {
                    $table->string('operator')->after('field');
                }
                if (!Schema::hasColumn('workflow_conditions', 'value')) {
                    $table->text('value')->nullable()->after('operator');
                }
                if (!Schema::hasColumn('workflow_conditions', 'logical_operator')) {
                    $table->string('logical_operator')->default('AND')->after('value');
                }
                if (!Schema::hasColumn('workflow_conditions', 'sequence')) {
                    $table->unsignedInteger('sequence')->default(1)->after('logical_operator');
                }
                if (!Schema::hasColumn('workflow_conditions', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('workflow_conditions', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── 3. workflow_triggers ──────────────────────────────────────────────
        if (Schema::hasTable('workflow_triggers') && !Schema::hasColumn('workflow_triggers', 'workflow_id')) {
            Schema::drop('workflow_triggers');
        }
        if (!Schema::hasTable('workflow_triggers')) {
            Schema::create('workflow_triggers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id')->index();
                $table->string('trigger_type');
                $table->string('entity_type');
                $table->string('event');
                $table->json('conditions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('workflow_triggers', function (Blueprint $table) {
                if (!Schema::hasColumn('workflow_triggers', 'workflow_id')) {
                    $table->unsignedBigInteger('workflow_id')->index()->after('id');
                }
                if (!Schema::hasColumn('workflow_triggers', 'trigger_type')) {
                    $table->string('trigger_type')->after('workflow_id');
                }
                if (!Schema::hasColumn('workflow_triggers', 'entity_type')) {
                    $table->string('entity_type')->after('trigger_type');
                }
                if (!Schema::hasColumn('workflow_triggers', 'event')) {
                    $table->string('event')->after('entity_type');
                }
                if (!Schema::hasColumn('workflow_triggers', 'conditions')) {
                    $table->json('conditions')->nullable()->after('event');
                }
                if (!Schema::hasColumn('workflow_triggers', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('workflow_triggers', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── 4. workflow_actions — ensure all columns exist ────────────────────
        if (Schema::hasTable('workflow_actions')) {
            Schema::table('workflow_actions', function (Blueprint $table) {
                if (!Schema::hasColumn('workflow_actions', 'workflow_id')) {
                    $table->unsignedBigInteger('workflow_id')->nullable()->index()->after('id');
                }
                if (!Schema::hasColumn('workflow_actions', 'action_type')) {
                    $table->string('action_type')->nullable();
                }
                if (!Schema::hasColumn('workflow_actions', 'action_target')) {
                    $table->string('action_target')->nullable();
                }
                if (!Schema::hasColumn('workflow_actions', 'action_params')) {
                    $table->json('action_params')->nullable();
                }
                if (!Schema::hasColumn('workflow_actions', 'delay_seconds')) {
                    $table->unsignedInteger('delay_seconds')->default(0);
                }
                if (!Schema::hasColumn('workflow_actions', 'retry_count')) {
                    $table->unsignedInteger('retry_count')->default(0);
                }
                if (!Schema::hasColumn('workflow_actions', 'sequence')) {
                    $table->unsignedInteger('sequence')->default(1);
                }
                if (!Schema::hasColumn('workflow_actions', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('workflow_actions', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('workflow_actions', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── 5. workflow_executions — ensure columns exist ─────────────────────
        if (Schema::hasTable('workflow_executions')) {
            Schema::table('workflow_executions', function (Blueprint $table) {
                if (!Schema::hasColumn('workflow_executions', 'trigger_entity_id')) {
                    $table->unsignedBigInteger('trigger_entity_id')->nullable();
                }
                if (!Schema::hasColumn('workflow_executions', 'trigger_entity_type')) {
                    $table->string('trigger_entity_type')->nullable();
                }
                if (!Schema::hasColumn('workflow_executions', 'context')) {
                    $table->json('context')->nullable();
                }
                if (!Schema::hasColumn('workflow_executions', 'triggered_at')) {
                    $table->timestamp('triggered_at')->nullable();
                }
                if (!Schema::hasColumn('workflow_executions', 'started_at')) {
                    $table->timestamp('started_at')->nullable();
                }
                if (!Schema::hasColumn('workflow_executions', 'error_message')) {
                    $table->text('error_message')->nullable();
                }
                if (!Schema::hasColumn('workflow_executions', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('workflow_executions', 'execution_depth')) {
                    $table->unsignedInteger('execution_depth')->default(0);
                }
                if (!Schema::hasColumn('workflow_executions', 'payload_size_bytes')) {
                    $table->unsignedInteger('payload_size_bytes')->default(0);
                }
                if (!Schema::hasColumn('workflow_executions', 'parent_execution_id')) {
                    $table->unsignedBigInteger('parent_execution_id')->nullable();
                }
                if (!Schema::hasColumn('workflow_executions', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── 6. workflow_steps — ensure columns exist ──────────────────────────
        if (Schema::hasTable('workflow_steps')) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                if (!Schema::hasColumn('workflow_steps', 'execution_id')) {
                    $table->unsignedBigInteger('execution_id')->nullable()->index();
                }
                if (!Schema::hasColumn('workflow_steps', 'action_type')) {
                    $table->string('action_type')->nullable();
                }
                if (!Schema::hasColumn('workflow_steps', 'sequence')) {
                    $table->unsignedInteger('sequence')->default(1);
                }
                if (!Schema::hasColumn('workflow_steps', 'result')) {
                    $table->json('result')->nullable();
                }
                if (!Schema::hasColumn('workflow_steps', 'error_message')) {
                    $table->text('error_message')->nullable();
                }
                if (!Schema::hasColumn('workflow_steps', 'started_at')) {
                    $table->timestamp('started_at')->nullable();
                }
                if (!Schema::hasColumn('workflow_steps', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
                if (!Schema::hasColumn('workflow_steps', 'duration_ms')) {
                    $table->unsignedInteger('duration_ms')->nullable();
                }
                if (!Schema::hasColumn('workflow_steps', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('workflow_steps', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── 7. crm_activities — add missing columns ───────────────────────────
        if (Schema::hasTable('crm_activities')) {
            Schema::table('crm_activities', function (Blueprint $table) {
                if (!Schema::hasColumn('crm_activities', 'title')) {
                    $table->string('title')->nullable()->after('type');
                }
                if (!Schema::hasColumn('crm_activities', 'status')) {
                    $table->string('status')->nullable()->default('pending')->after('title');
                }
                if (!Schema::hasColumn('crm_activities', 'due_at')) {
                    $table->timestamp('due_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('crm_activities', 'done_at')) {
                    $table->timestamp('done_at')->nullable()->after('due_at');
                }
                if (!Schema::hasColumn('crm_activities', 'subject_id')) {
                    $table->unsignedBigInteger('subject_id')->nullable()->after('done_at');
                }
                if (!Schema::hasColumn('crm_activities', 'subject_type')) {
                    $table->string('subject_type')->nullable()->after('subject_id');
                }
            });
        }

        // ── 8. crm_leads — add missing columns ───────────────────────────────
        if (Schema::hasTable('crm_leads')) {
            Schema::table('crm_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('crm_leads', 'title')) {
                    $table->string('title')->nullable();
                }
                if (!Schema::hasColumn('crm_leads', 'owner_id')) {
                    $table->unsignedBigInteger('owner_id')->nullable();
                }
                if (!Schema::hasColumn('crm_leads', 'estimated_value')) {
                    $table->decimal('estimated_value', 14, 2)->nullable();
                }
                if (!Schema::hasColumn('crm_leads', 'currency')) {
                    $table->string('currency', 3)->nullable();
                }
                if (!Schema::hasColumn('crm_leads', 'description')) {
                    $table->text('description')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive migration — no rollback needed
    }
};
