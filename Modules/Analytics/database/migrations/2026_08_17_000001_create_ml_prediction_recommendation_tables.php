<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ─── 9 new tables (models/factories/controllers already real, migrations never existed) ───

        if (! Schema::hasTable('prediction_models')) {
            Schema::create('prediction_models', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('model_name', 128);
                $table->string('model_type', 64)->nullable();
                $table->string('status', 16)->default('training')->index();
                $table->text('description')->nullable();
                $table->json('configuration')->nullable();
                $table->decimal('training_accuracy', 5, 4)->nullable();
                $table->decimal('validation_accuracy', 5, 4)->nullable();
                $table->timestamp('trained_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->unsignedInteger('prediction_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('prediction_inputs')) {
            Schema::create('prediction_inputs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prediction_model_id')->constrained('prediction_models')->cascadeOnDelete();
                $table->string('feature_name', 128);
                $table->string('feature_type', 32)->nullable();
                $table->string('data_source', 128)->nullable();
                $table->string('field_mapping', 128)->nullable();
                $table->json('transformation')->nullable();
                $table->decimal('importance_score', 5, 4)->nullable();
                $table->boolean('is_required')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prediction_results')) {
            Schema::create('prediction_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prediction_model_id')->constrained('prediction_models')->cascadeOnDelete();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('predictable_type')->nullable();
                $table->unsignedBigInteger('predictable_id')->nullable();
                $table->decimal('prediction_score', 5, 4)->nullable();
                $table->string('prediction_class', 64)->nullable();
                $table->json('feature_contributions')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('predicted_at')->nullable();
                $table->timestamp('actual_outcome_at')->nullable();
                $table->string('actual_outcome', 64)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['predictable_type', 'predictable_id']);
            });
        }

        if (! Schema::hasTable('model_accuracy_metrics')) {
            Schema::create('model_accuracy_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prediction_model_id')->constrained('prediction_models')->cascadeOnDelete();
                $table->timestamp('metric_date')->nullable();
                $table->string('metric_type', 64)->nullable();
                $table->decimal('metric_value', 10, 4)->nullable();
                $table->unsignedInteger('sample_size')->nullable();
                $table->json('breakdown_by_segment')->nullable();
                $table->string('data_period', 32)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('model_metrics')) {
            Schema::create('model_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ml_model_version_id')->nullable()->constrained('ml_model_versions')->nullOnDelete();
                $table->string('metric_name', 64);
                $table->decimal('metric_value', 12, 6)->nullable();
                $table->string('dataset_type', 32)->nullable();
                $table->json('breakdown')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recommendation_models')) {
            Schema::create('recommendation_models', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('model_name', 128);
                $table->string('recommendation_type', 64)->nullable();
                $table->string('algorithm', 64)->nullable();
                $table->string('status', 16)->default('training')->index();
                $table->text('description')->nullable();
                $table->json('configuration')->nullable();
                $table->decimal('coverage_percentage', 5, 2)->nullable();
                $table->unsignedInteger('recommendation_count')->default(0);
                $table->unsignedInteger('click_through_count')->default(0);
                $table->decimal('ctr', 5, 4)->nullable();
                $table->timestamp('last_trained_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('recommendations')) {
            Schema::create('recommendations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recommendation_model_id')->constrained('recommendation_models')->cascadeOnDelete();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('recipient_type')->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('recommended_type')->nullable();
                $table->unsignedBigInteger('recommended_id')->nullable();
                $table->decimal('relevance_score', 5, 4)->nullable();
                $table->unsignedInteger('rank')->nullable();
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['recipient_type', 'recipient_id']);
                $table->index(['recommended_type', 'recommended_id']);
            });
        }

        if (! Schema::hasTable('ab_test_runs')) {
            Schema::create('ab_test_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('ml_model_id')->nullable()->constrained('ml_models')->nullOnDelete();
                $table->unsignedBigInteger('control_version_id')->nullable();
                $table->unsignedBigInteger('variant_version_id')->nullable();
                $table->string('test_name', 128)->nullable();
                $table->text('hypothesis')->nullable();
                $table->string('status', 16)->default('running')->index();
                $table->unsignedInteger('sample_size')->nullable();
                $table->decimal('test_split', 5, 2)->nullable();
                $table->decimal('statistical_significance', 5, 4)->nullable();
                $table->decimal('confidence_level', 5, 2)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->json('results')->nullable();
                $table->string('winner', 16)->nullable();
                $table->text('conclusion')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('control_version_id')->references('id')->on('ml_model_versions')->nullOnDelete();
                $table->foreign('variant_version_id')->references('id')->on('ml_model_versions')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('user_interactions')) {
            Schema::create('user_interactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('user_type')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('interacted_item_type')->nullable();
                $table->unsignedBigInteger('interacted_item_id')->nullable();
                $table->string('interaction_type', 32)->nullable();
                $table->decimal('engagement_score', 5, 4)->nullable();
                $table->json('context')->nullable();
                $table->timestamp('interacted_at')->nullable();
                $table->timestamps();
                $table->index(['user_type', 'user_id']);
                $table->index(['interacted_item_type', 'interacted_item_id']);
            });
        }

        // ─── Patches to 6 existing tables (created against an earlier, generic schema
        // that drifted from what the real models now declare) ───

        if (Schema::hasTable('ml_models')) {
            if (Schema::hasColumn('ml_models', 'tenant_id')) {
                // `tenant_id` is NOT NULL with no default, but the real model/factory
                // write `company_id` instead — every insert that doesn't also stuff a
                // tenant_id was failing. Same pattern as B6's sales_order_lines.product_name.
                Schema::table('ml_models', function (Blueprint $table) {
                    $table->string('tenant_id', 36)->nullable()->change();
                });
            }
            if (Schema::hasColumn('ml_models', 'model_type')) {
                // Same reasoning: NOT NULL with no default, not in the model's real
                // $fillable (which uses `model_category` instead).
                Schema::table('ml_models', function (Blueprint $table) {
                    $table->string('model_type', 64)->nullable()->change();
                });
            }
            if (Schema::hasColumn('ml_models', 'module')) {
                // Same reasoning: NOT NULL with no default, not in the model's real
                // $fillable at all.
                Schema::table('ml_models', function (Blueprint $table) {
                    $table->string('module', 64)->nullable()->change();
                });
            }

            Schema::table('ml_models', function (Blueprint $table) {
                foreach ([
                    'company_id' => fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable()->index(),
                    'model_key' => fn (Blueprint $t) => $t->string('model_key', 128)->nullable(),
                    'model_name' => fn (Blueprint $t) => $t->string('model_name', 128)->nullable(),
                    'model_category' => fn (Blueprint $t) => $t->string('model_category', 64)->nullable(),
                    'framework' => fn (Blueprint $t) => $t->string('framework', 64)->nullable(),
                    'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
                    'production_version' => fn (Blueprint $t) => $t->string('production_version', 16)->nullable(),
                    'total_versions' => fn (Blueprint $t) => $t->unsignedInteger('total_versions')->default(0),
                    'production_accuracy' => fn (Blueprint $t) => $t->decimal('production_accuracy', 5, 4)->nullable(),
                    'deployed_at' => fn (Blueprint $t) => $t->timestamp('deployed_at')->nullable(),
                    'last_retrained_at' => fn (Blueprint $t) => $t->timestamp('last_retrained_at')->nullable(),
                    'inference_count' => fn (Blueprint $t) => $t->unsignedInteger('inference_count')->default(0),
                    'avg_inference_time_ms' => fn (Blueprint $t) => $t->decimal('avg_inference_time_ms', 10, 2)->nullable(),
                    'created_by' => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
                    'deployed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('deployed_by')->nullable(),
                    // Model uses SoftDeletes but the original migration never added the column.
                    'deleted_at' => fn (Blueprint $t) => $t->softDeletes(),
                ] as $column => $adder) {
                    if (! Schema::hasColumn('ml_models', $column)) {
                        $adder($table);
                    }
                }
            });
        }

        // NOTE: the `anomaly_detection_models`/`detected_anomalies` patch blocks that
        // used to live here were removed along with those two tables — Analytics' own
        // anomaly-detection registry was an orphaned duplicate of the AI module's real,
        // routed anomaly detection (Modules\AI\Services\AiAnomalyDetectionService /
        // AiAnomalyController). See the migrations that created those tables (deleted)
        // and Modules/Analytics/routes/api.php for the full removal note.

        if (Schema::hasTable('ml_model_versions')) {
            if (! Schema::hasColumn('ml_model_versions', 'updated_at')) {
                // Model doesn't disable timestamps ($timestamps defaults to true), but the
                // original migration only added `created_at` (useCurrent(), no full
                // timestamps()) — every save() attempt failed writing updated_at.
                Schema::table('ml_model_versions', function (Blueprint $table) {
                    $table->timestamp('updated_at')->nullable();
                });
            }
            if (Schema::hasColumn('ml_model_versions', 'version')) {
                // `version` is NOT NULL with no default, but the model's real $fillable
                // uses `version_number` instead — every insert was failing on it.
                Schema::table('ml_model_versions', function (Blueprint $table) {
                    $table->string('version', 16)->nullable()->change();
                });
            }

            Schema::table('ml_model_versions', function (Blueprint $table) {
                foreach ([
                    'version_number' => fn (Blueprint $t) => $t->string('version_number', 16)->nullable(),
                    'change_notes' => fn (Blueprint $t) => $t->text('change_notes')->nullable(),
                    'validation_accuracy' => fn (Blueprint $t) => $t->decimal('validation_accuracy', 5, 4)->nullable(),
                    'validation_precision' => fn (Blueprint $t) => $t->decimal('validation_precision', 5, 4)->nullable(),
                    'validation_recall' => fn (Blueprint $t) => $t->decimal('validation_recall', 5, 4)->nullable(),
                    'validation_f1' => fn (Blueprint $t) => $t->decimal('validation_f1', 5, 4)->nullable(),
                    'training_samples' => fn (Blueprint $t) => $t->unsignedInteger('training_samples')->nullable(),
                    'validation_samples' => fn (Blueprint $t) => $t->unsignedInteger('validation_samples')->nullable(),
                    'trained_at' => fn (Blueprint $t) => $t->timestamp('trained_at')->nullable(),
                    'model_path' => fn (Blueprint $t) => $t->string('model_path', 512)->nullable(),
                    'training_config' => fn (Blueprint $t) => $t->json('training_config')->nullable(),
                    'activated_at' => fn (Blueprint $t) => $t->timestamp('activated_at')->nullable(),
                    'deactivated_at' => fn (Blueprint $t) => $t->timestamp('deactivated_at')->nullable(),
                    'created_by' => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
                ] as $column => $adder) {
                    if (! Schema::hasColumn('ml_model_versions', $column)) {
                        $adder($table);
                    }
                }
            });
        }

        if (Schema::hasTable('forecast_alerts')) {
            Schema::table('forecast_alerts', function (Blueprint $table) {
                foreach ([
                    // Additive: original migration named this column `forecast_model_id`,
                    // the model $fillable uses `model_id` — added separately rather than renamed.
                    'model_id' => fn (Blueprint $t) => $t->unsignedBigInteger('model_id')->nullable(),
                    'tenant_id' => fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable()->index(),
                    'title' => fn (Blueprint $t) => $t->string('title', 200)->nullable(),
                    'predicted_date' => fn (Blueprint $t) => $t->date('predicted_date')->nullable(),
                    'predicted_value' => fn (Blueprint $t) => $t->decimal('predicted_value', 18, 4)->nullable(),
                    'threshold_value' => fn (Blueprint $t) => $t->decimal('threshold_value', 18, 4)->nullable(),
                    'is_acknowledged' => fn (Blueprint $t) => $t->boolean('is_acknowledged')->default(false),
                    'acknowledged_by' => fn (Blueprint $t) => $t->unsignedBigInteger('acknowledged_by')->nullable(),
                    'acknowledged_at' => fn (Blueprint $t) => $t->timestamp('acknowledged_at')->nullable(),
                ] as $column => $adder) {
                    if (! Schema::hasColumn('forecast_alerts', $column)) {
                        $adder($table);
                    }
                }
            });
        }

        if (Schema::hasTable('forecast_predictions')) {
            Schema::table('forecast_predictions', function (Blueprint $table) {
                foreach ([
                    'model_id' => fn (Blueprint $t) => $t->unsignedBigInteger('model_id')->nullable(),
                    'tenant_id' => fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable()->index(),
                    // Additive: original migration named these `upper_bound`/`error_rate`,
                    // the model $fillable uses `predicted_upper_bound`/`error_pct`.
                    'predicted_upper_bound' => fn (Blueprint $t) => $t->decimal('predicted_upper_bound', 18, 4)->nullable(),
                    'error_pct' => fn (Blueprint $t) => $t->decimal('error_pct', 8, 4)->nullable(),
                    'confidence' => fn (Blueprint $t) => $t->decimal('confidence', 5, 4)->nullable(),
                    'metadata' => fn (Blueprint $t) => $t->json('metadata')->nullable(),
                ] as $column => $adder) {
                    if (! Schema::hasColumn('forecast_predictions', $column)) {
                        $adder($table);
                    }
                }
            });
        }

        if (Schema::hasTable('forecast_scenarios')) {
            Schema::table('forecast_scenarios', function (Blueprint $table) {
                foreach ([
                    'tenant_id' => fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable()->index(),
                    'base_model_id' => fn (Blueprint $t) => $t->unsignedBigInteger('base_model_id')->nullable(),
                    'created_by' => fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
                ] as $column => $adder) {
                    if (! Schema::hasColumn('forecast_scenarios', $column)) {
                        $adder($table);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_interactions');
        Schema::dropIfExists('ab_test_runs');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('recommendation_models');
        Schema::dropIfExists('model_metrics');
        Schema::dropIfExists('model_accuracy_metrics');
        Schema::dropIfExists('prediction_results');
        Schema::dropIfExists('prediction_inputs');
        Schema::dropIfExists('prediction_models');
    }
};
