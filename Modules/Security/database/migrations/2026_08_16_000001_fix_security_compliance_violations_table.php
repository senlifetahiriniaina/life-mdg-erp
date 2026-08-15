<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('security_compliance_violations', function (Blueprint $table) {
            if (! Schema::hasColumn('security_compliance_violations', 'company_id')) {
                // string(36), matching every sibling Security model's company_id column
                // (SecurityIncident, EncryptionKey, ComplianceControl, TrustZone) — App\Models\Company's
                // bigint id is stored here as its string form, same as those tables already do.
                $table->string('company_id', 36)->nullable()->after('id')->index();
            }

            // ComplianceViolation::$fillable/its factory use these column names; the original
            // migration used description/status/resolved_at instead — never renamed since those
            // columns aren't dropped (additive-only), just unused by the model going forward.
            if (! Schema::hasColumn('security_compliance_violations', 'violation_description')) {
                $table->text('violation_description')->nullable()->after('violation_type');
            }
            if (! Schema::hasColumn('security_compliance_violations', 'violation_status')) {
                $table->string('violation_status', 32)->default('open')->index()->after('severity');
            }
            if (! Schema::hasColumn('security_compliance_violations', 'remediation_deadline')) {
                $table->timestamp('remediation_deadline')->nullable()->after('detected_at');
            }
            if (! Schema::hasColumn('security_compliance_violations', 'remediated_at')) {
                $table->timestamp('remediated_at')->nullable()->after('remediation_deadline');
            }
            if (! Schema::hasColumn('security_compliance_violations', 'remediation_notes')) {
                $table->text('remediation_notes')->nullable()->after('remediated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('security_compliance_violations', function (Blueprint $table) {
            foreach (['company_id', 'violation_description', 'violation_status', 'remediation_deadline', 'remediated_at', 'remediation_notes'] as $column) {
                if (Schema::hasColumn('security_compliance_violations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
