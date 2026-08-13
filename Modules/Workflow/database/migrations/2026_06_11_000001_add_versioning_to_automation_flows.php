<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds flow versioning support:
 *   - version_number : current draft version counter
 *   - is_published   : true when the flow has been published at least once
 *   - parent_version_id: FK to flow_versions for the currently-active snapshot
 *
 * The flow_versions table stores immutable snapshots of nodes + connections.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add columns to automation_flows
        if (Schema::hasTable('automation_flows')) {
            Schema::table('automation_flows', function (Blueprint $table) {
                if (!Schema::hasColumn('automation_flows', 'version_number')) {
                    $table->unsignedInteger('version_number')->default(1)->after('version');
                }
                if (!Schema::hasColumn('automation_flows', 'is_published')) {
                    $table->boolean('is_published')->default(false)->after('version_number');
                }
                if (!Schema::hasColumn('automation_flows', 'parent_version_id')) {
                    $table->unsignedBigInteger('parent_version_id')->nullable()->after('is_published');
                }
            });
        }

        // Create flow_versions snapshot table
        if (!Schema::hasTable('flow_versions')) {
            Schema::create('flow_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id')->index();
                $table->unsignedInteger('version_number');
                $table->string('label')->nullable()->comment('Optional human tag e.g. "v2 — added approval step"');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->json('nodes_snapshot')->comment('Serialised AutomationNode rows at publish time');
                $table->json('connections_snapshot')->comment('Serialised AutomationConnection rows at publish time');
                $table->json('flow_meta')->nullable()->comment('Flow-level fields snapshot (name, trigger_type, etc.)');
                $table->timestamps();

                $table->unique(['flow_id', 'version_number']);

                $table->foreign('flow_id')
                    ->references('id')
                    ->on('automation_flows')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_versions');

        if (Schema::hasTable('automation_flows')) {
            Schema::table('automation_flows', function (Blueprint $table) {
                foreach (['parent_version_id', 'is_published', 'version_number'] as $col) {
                    if (Schema::hasColumn('automation_flows', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
