<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('crm_workflow_nodes')) {
            Schema::create('crm_workflow_nodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('crm_workflows')->cascadeOnDelete();
                $table->string('node_id', 64);
                $table->string('type', 32);
                $table->string('name');
                $table->json('config')->nullable();
                $table->integer('position_x')->default(0);
                $table->integer('position_y')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_workflow_edges')) {
            Schema::create('crm_workflow_edges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('crm_workflows')->cascadeOnDelete();
                $table->string('from_node_id', 64);
                $table->string('to_node_id', 64);
                $table->string('condition', 64)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_workflow_executions')) {
            Schema::create('crm_workflow_executions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('crm_workflows')->cascadeOnDelete();
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('execution_trace')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_revenue_trends')) {
            Schema::create('crm_revenue_trends', function (Blueprint $table) {
                $table->id();
                $table->string('metric_name', 64);
                $table->string('dimension', 64)->nullable();
                $table->string('dimension_value')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('current_value', 15, 2)->default(0);
                $table->decimal('previous_value', 15, 2)->default(0);
                $table->decimal('change_pct', 8, 2)->default(0);
                $table->string('trend_direction', 16)->nullable();
                $table->unsignedInteger('data_points_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_revenue_anomalies')) {
            Schema::create('crm_revenue_anomalies', function (Blueprint $table) {
                $table->id();
                $table->string('anomaly_type', 32);
                $table->string('metric_name', 64);
                $table->string('dimension', 64)->nullable();
                $table->string('dimension_value')->nullable();
                $table->decimal('detected_value', 15, 2)->default(0);
                $table->decimal('expected_value', 15, 2)->default(0);
                $table->decimal('deviation_pct', 8, 2)->default(0);
                $table->string('severity', 16);
                $table->string('status', 20)->default('detected');
                $table->text('explanation')->nullable();
                $table->timestamp('detected_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_revenue_anomalies');
        Schema::dropIfExists('crm_revenue_trends');
        Schema::dropIfExists('crm_workflow_executions');
        Schema::dropIfExists('crm_workflow_edges');
        Schema::dropIfExists('crm_workflow_nodes');
    }
};
