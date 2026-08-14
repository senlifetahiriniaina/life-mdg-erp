<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// security_threat_indicators has indexes on indicator_type/is_whitelisted but
// none covering indicator_value itself, so ThreatDetectionService::isKnownThreatIp()
// was an unindexed scan on that column. RequestInspectionMiddleware (mini-WAF)
// caches the active blocklist rather than querying per-request, but this index
// still matters for the cache-rebuild query and any other direct caller.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_threat_indicators')
            && ! Schema::hasIndex('security_threat_indicators', 'security_threat_indicators_type_value_whitelisted_index')) {
            Schema::table('security_threat_indicators', function (Blueprint $table) {
                $table->index(
                    ['indicator_type', 'indicator_value', 'is_whitelisted'],
                    'security_threat_indicators_type_value_whitelisted_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('security_threat_indicators')) {
            Schema::table('security_threat_indicators', function (Blueprint $table) {
                $table->dropIndex('security_threat_indicators_type_value_whitelisted_index');
            });
        }
    }
};
