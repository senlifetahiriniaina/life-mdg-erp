<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add PII encryption columns to hr_employees table.
     * GDPR compliance: Encrypt sensitive personal data at rest.
     */
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            // Add encrypted versions of PII fields (if not already present)
            if (!Schema::hasColumn('hr_employees', 'national_id_encrypted')) {
                $table->text('national_id_encrypted')->nullable()->after('national_id');
            }
            if (!Schema::hasColumn('hr_employees', 'passport_number_encrypted')) {
                $table->text('passport_number_encrypted')->nullable()->after('passport_number');
            }
            if (!Schema::hasColumn('hr_employees', 'bank_details_encrypted')) {
                $table->text('bank_details_encrypted')->nullable()->after('bank_details');
            }
            if (!Schema::hasColumn('hr_employees', 'emergency_contacts_encrypted')) {
                $table->text('emergency_contacts_encrypted')->nullable()->after('emergency_contacts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumnIfExists([
                'national_id_encrypted',
                'passport_number_encrypted',
                'bank_details_encrypted',
                'emergency_contacts_encrypted',
            ]);
        });
    }
};
