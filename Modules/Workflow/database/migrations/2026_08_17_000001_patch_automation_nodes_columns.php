<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('automation_nodes')) {
            return;
        }

        Schema::table('automation_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('automation_nodes', 'node_type')) {
                // Additive: the original migration named this column `type`, the model
                // $fillable uses `node_type` — added as a new column rather than
                // renaming (additive-only migration policy, same as elsewhere in this chantier).
                $table->string('node_type', 50)->nullable()->after('flow_id');
            }
            if (! Schema::hasColumn('automation_nodes', 'node_key')) {
                $table->string('node_key', 150)->nullable();
            }
            if (! Schema::hasColumn('automation_nodes', 'label')) {
                $table->string('label', 150)->nullable();
            }
            if (! Schema::hasColumn('automation_nodes', 'input_schema')) {
                $table->text('input_schema')->nullable();
            }
            if (! Schema::hasColumn('automation_nodes', 'output_schema')) {
                $table->text('output_schema')->nullable();
            }
            if (! Schema::hasColumn('automation_nodes', 'error_handling')) {
                $table->string('error_handling', 20)->default('stop');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('automation_nodes')) {
            return;
        }

        Schema::table('automation_nodes', function (Blueprint $table) {
            $table->dropColumn(['node_type', 'node_key', 'label', 'input_schema', 'output_schema', 'error_handling']);
        });
    }
};
