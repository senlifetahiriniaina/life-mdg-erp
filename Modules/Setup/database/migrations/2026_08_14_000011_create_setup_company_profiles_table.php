<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules\Setup\Models\CompanyProfile — previously referenced by
 * AdminCompanyController (show/update/uploadLogo) but never created,
 * a guaranteed fatal on first call. Also replaces SetupWizardService's
 * cache-only state (Cache::put, 24h TTL, lost on flush/restart) with real
 * persistence for all 6 wizard steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('setup_company_profiles')) {
            return;
        }

        Schema::create('setup_company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->unique();
            $table->string('company_name');
            $table->string('legal_name')->nullable();
            $table->string('company_type')->nullable();
            $table->string('industry')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('timezone')->nullable();
            $table->unsignedTinyInteger('fiscal_year_start')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('logo_path')->nullable();

            // Replace SetupWizardService's cache-only steps 2/3/4/5 with
            // durable storage — the wizard state survives a cache flush and
            // leaves an auditable onboarding record.
            $table->json('admin_profile')->nullable();
            $table->json('modules_selected')->nullable();
            $table->json('workflows_config')->nullable();
            $table->json('apps_config')->nullable();

            $table->boolean('onboarding_completed')->default(false);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_company_profiles');
    }
};
