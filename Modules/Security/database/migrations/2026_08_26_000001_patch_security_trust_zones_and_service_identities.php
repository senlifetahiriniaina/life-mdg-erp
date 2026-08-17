<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.3 (Core + Security) — TrustZoneController validated and wrote
 * field names (name/trust_level/ip_ranges/policies) that matched neither the
 * real migrated table nor even TrustZone's own $fillable (which already
 * declared zone_type/cidr_blocks/device_policies/authentication_policies/
 * trust_score_minimum, but the original migration never created those
 * columns at all). Patches the table additively to match the model's real
 * $fillable/$casts, plus a new assigned_resources column the controller's
 * assignResource() action needs and had nowhere real to write. The
 * controller was rewritten in the same pass to stop writing the old ad-hoc
 * field names. ServiceIdentityController had the same shape of bug (writing
 * name/service/client_id/client_secret/status against a table that only has
 * service_name/service_type/public_key/private_key_hash) but its real
 * columns already existed — that controller was rewritten to match the
 * existing schema instead of needing a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_trust_zones', function (Blueprint $table) {
            if (! Schema::hasColumn('security_trust_zones', 'zone_type')) {
                $table->string('zone_type', 32)->nullable()->after('zone_name');
            }
            if (! Schema::hasColumn('security_trust_zones', 'description')) {
                $table->text('description')->nullable()->after('zone_type');
            }
            if (! Schema::hasColumn('security_trust_zones', 'cidr_blocks')) {
                $table->json('cidr_blocks')->nullable()->after('description');
            }
            if (! Schema::hasColumn('security_trust_zones', 'device_policies')) {
                $table->json('device_policies')->nullable()->after('cidr_blocks');
            }
            if (! Schema::hasColumn('security_trust_zones', 'authentication_policies')) {
                $table->json('authentication_policies')->nullable()->after('device_policies');
            }
            if (! Schema::hasColumn('security_trust_zones', 'trust_score_minimum')) {
                $table->unsignedInteger('trust_score_minimum')->nullable()->after('authentication_policies');
            }
            if (! Schema::hasColumn('security_trust_zones', 'assigned_resources')) {
                $table->json('assigned_resources')->nullable()->after('trust_score_minimum');
            }
            if (! Schema::hasColumn('security_trust_zones', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('security_trust_zones', function (Blueprint $table) {
            $table->dropColumn(['zone_type', 'description', 'cidr_blocks', 'device_policies', 'authentication_policies', 'trust_score_minimum', 'assigned_resources']);
        });
    }
};
