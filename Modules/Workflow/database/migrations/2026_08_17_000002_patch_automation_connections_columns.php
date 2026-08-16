<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * automation_connections was created without condition_type/condition_expr,
 * but AutomationConnection::$fillable and FlowExecutionEngine::resolveNextNodes()
 * both read these two columns for real branching logic -- every connection
 * write via the API/template seeder throws, and every fetched connection's
 * condition_type is null, so match() always falls to default => false.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automation_connections')) {
            return;
        }

        Schema::table('automation_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('automation_connections', 'condition_type')) {
                $table->string('condition_type', 20)->default('always')->after('target_node_id');
            }
            if (! Schema::hasColumn('automation_connections', 'condition_expr')) {
                $table->text('condition_expr')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('automation_connections')) {
            return;
        }

        Schema::table('automation_connections', function (Blueprint $table) {
            $table->dropColumn(['condition_type', 'condition_expr']);
        });
    }
};
