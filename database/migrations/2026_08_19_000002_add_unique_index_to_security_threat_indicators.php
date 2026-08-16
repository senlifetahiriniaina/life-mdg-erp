<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IncidentController::storeThreat() already validates
 * 'indicator_value' => 'unique:security_threat_indicators' at the app
 * layer, but nothing enforced it at the schema layer -- any other write
 * path (ThreatDetectionService, seeders, a future job) could still insert
 * a duplicate indicator value. Adding the matching DB-level constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_threat_indicators')) {
            Schema::table('security_threat_indicators', function (Blueprint $table) {
                $table->unique('indicator_value');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('security_threat_indicators')) {
            Schema::table('security_threat_indicators', function (Blueprint $table) {
                $table->dropUnique(['indicator_value']);
            });
        }
    }
};
