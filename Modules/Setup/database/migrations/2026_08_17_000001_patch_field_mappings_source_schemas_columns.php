<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('setup_field_mappings')) {
            Schema::table('setup_field_mappings', function (Blueprint $table) {
                if (! Schema::hasColumn('setup_field_mappings', 'target_table')) {
                    $table->string('target_table', 128)->nullable();
                }
                if (! Schema::hasColumn('setup_field_mappings', 'source_sample')) {
                    $table->json('source_sample')->nullable();
                }
                if (! Schema::hasColumn('setup_field_mappings', 'is_ai_suggested')) {
                    // Additive: the original migration named this column
                    // `ai_suggested`, the model's real $fillable uses `is_ai_suggested`.
                    $table->boolean('is_ai_suggested')->default(false);
                }
                if (! Schema::hasColumn('setup_field_mappings', 'is_confirmed')) {
                    // Additive: the original migration named this column
                    // `user_confirmed`, the model's real $fillable uses `is_confirmed`.
                    $table->boolean('is_confirmed')->default(false);
                }
            });
        }

        if (Schema::hasTable('setup_source_schemas')) {
            Schema::table('setup_source_schemas', function (Blueprint $table) {
                if (! Schema::hasColumn('setup_source_schemas', 'detected_columns')) {
                    // Additive: the original migration named this column `columns`,
                    // the model's real $fillable uses `detected_columns`.
                    $table->json('detected_columns')->nullable();
                }
                if (! Schema::hasColumn('setup_source_schemas', 'row_count')) {
                    // Additive: the original migration named this column
                    // `total_rows`, the model's real $fillable uses `row_count`.
                    $table->unsignedInteger('row_count')->default(0);
                }
                if (! Schema::hasColumn('setup_source_schemas', 'sheet_names')) {
                    $table->json('sheet_names')->nullable();
                }
            });

            // Legacy NOT NULL column superseded by `detected_columns` above —
            // the model never populates it.
            Schema::table('setup_source_schemas', function (Blueprint $table) {
                $table->json('columns')->nullable()->change();
            });
        }

        if (Schema::hasTable('setup_onboarding_sessions')) {
            // The model's docblock/business logic treat total_duration_seconds as
            // legitimately nullable (an in-progress session has no duration yet) —
            // the original migration made it NOT NULL with a default(0) instead.
            Schema::table('setup_onboarding_sessions', function (Blueprint $table) {
                $table->unsignedInteger('total_duration_seconds')->nullable()->default(null)->change();
            });
        }

        if (Schema::hasTable('setup_onboarding_step_events')) {
            Schema::table('setup_onboarding_step_events', function (Blueprint $table) {
                if (! Schema::hasColumn('setup_onboarding_step_events', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                }
                if (! Schema::hasColumn('setup_onboarding_step_events', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (! Schema::hasColumn('setup_onboarding_step_events', 'step')) {
                    // Additive: the original migration named this column
                    // `step_name` (string), the model's real $fillable uses
                    // `step` (int).
                    $table->unsignedInteger('step')->nullable();
                }
                if (! Schema::hasColumn('setup_onboarding_step_events', 'event')) {
                    // Additive: the original migration named this column
                    // `event_type`, the model's real $fillable uses `event`.
                    $table->string('event', 32)->nullable();
                }
                if (! Schema::hasColumn('setup_onboarding_step_events', 'metadata')) {
                    // Additive: the original migration named this column
                    // `step_data`, the model's real $fillable uses `metadata`.
                    $table->json('metadata')->nullable();
                }
            });

            // Legacy NOT NULL column superseded by `step`/`event` above — no real
            // code path populates it anymore.
            Schema::table('setup_onboarding_step_events', function (Blueprint $table) {
                $table->string('step_name', 32)->nullable()->change();
            });
        }

        if (! Schema::hasTable('setup_onboarding_funnel_snapshots')) {
            // The model (FunnelSnapshot) points at this table name, but the
            // original migration created `setup_funnel_snapshots` instead
            // (different name AND different columns) — the real table was
            // never created. Left the orphaned legacy table alone (additive-only
            // policy) and create the one the model actually uses.
            Schema::create('setup_onboarding_funnel_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->date('snapshot_date');
                $table->unsignedInteger('sessions_started')->default(0);
                $table->unsignedInteger('sessions_completed')->default(0);
                $table->unsignedInteger('sessions_abandoned')->default(0);
                $table->unsignedInteger('avg_duration_seconds')->nullable();
                $table->unsignedInteger('median_duration_seconds')->nullable();
                $table->decimal('step1_completion_rate', 5, 2)->nullable();
                $table->decimal('step2_completion_rate', 5, 2)->nullable();
                $table->decimal('step3_completion_rate', 5, 2)->nullable();
                $table->decimal('step4_completion_rate', 5, 2)->nullable();
                $table->decimal('step5_completion_rate', 5, 2)->nullable();
                $table->decimal('ai_mapping_adoption_rate', 5, 2)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique(['tenant_id', 'snapshot_date']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('setup_field_mappings')) {
            Schema::table('setup_field_mappings', function (Blueprint $table) {
                $table->dropColumn(['target_table', 'source_sample', 'is_ai_suggested', 'is_confirmed']);
            });
        }

        if (Schema::hasTable('setup_source_schemas')) {
            Schema::table('setup_source_schemas', function (Blueprint $table) {
                $table->dropColumn(['detected_columns', 'row_count', 'sheet_names']);
            });
        }

        if (Schema::hasTable('setup_onboarding_sessions')) {
            Schema::table('setup_onboarding_sessions', function (Blueprint $table) {
                $table->unsignedInteger('total_duration_seconds')->default(0)->change();
            });
        }

        if (Schema::hasTable('setup_onboarding_step_events')) {
            Schema::table('setup_onboarding_step_events', function (Blueprint $table) {
                $table->dropColumn(['tenant_id', 'user_id', 'step', 'event', 'metadata']);
            });
        }

        Schema::dropIfExists('setup_onboarding_funnel_snapshots');
    }
};
