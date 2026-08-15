<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('csp_violations')) {
            Schema::create('csp_violations', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('document_uri')->nullable();
                $table->string('violated_directive')->nullable();
                $table->string('effective_directive')->nullable();
                $table->text('original_policy')->nullable();
                $table->string('disposition', 20)->default('enforce');
                $table->string('blocked_uri')->nullable();
                $table->string('source_file')->nullable();
                $table->unsignedInteger('line_number')->nullable();
                $table->unsignedInteger('column_number')->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('tenant_id', 36)->nullable()->index();
                $table->string('module', 50)->nullable();
                $table->json('violation_data')->nullable();
                $table->boolean('is_internal_request')->default(false);
                $table->string('severity', 16)->default('medium')->index();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                foreach ([
                    'uuid' => fn (Blueprint $t) => $t->string('uuid', 36)->nullable(),
                    'legal_name' => fn (Blueprint $t) => $t->string('legal_name')->nullable(),
                    'company_type' => fn (Blueprint $t) => $t->string('company_type', 20)->nullable(),
                    'country_code' => fn (Blueprint $t) => $t->string('country_code', 2)->nullable(),
                    'region' => fn (Blueprint $t) => $t->string('region')->nullable(),
                    'city' => fn (Blueprint $t) => $t->string('city')->nullable(),
                    'currency' => fn (Blueprint $t) => $t->string('currency', 3)->nullable(),
                    'timezone' => fn (Blueprint $t) => $t->string('timezone')->nullable(),
                    'locale' => fn (Blueprint $t) => $t->string('locale', 5)->nullable(),
                    'industry' => fn (Blueprint $t) => $t->string('industry')->nullable(),
                    'plan_expires_at' => fn (Blueprint $t) => $t->timestamp('plan_expires_at')->nullable(),
                    'status' => fn (Blueprint $t) => $t->string('status', 16)->default('trial'),
                    'db_name' => fn (Blueprint $t) => $t->string('db_name')->nullable(),
                    'db_host' => fn (Blueprint $t) => $t->string('db_host')->nullable(),
                    'db_port' => fn (Blueprint $t) => $t->unsignedInteger('db_port')->nullable(),
                    'onboarding_step' => fn (Blueprint $t) => $t->unsignedTinyInteger('onboarding_step')->default(0),
                    'owner_id' => fn (Blueprint $t) => $t->unsignedBigInteger('owner_id')->nullable(),
                    'contact_email' => fn (Blueprint $t) => $t->string('contact_email')->nullable(),
                    'contact_phone' => fn (Blueprint $t) => $t->string('contact_phone')->nullable(),
                    'logo_url' => fn (Blueprint $t) => $t->string('logo_url')->nullable(),
                    'primary_color' => fn (Blueprint $t) => $t->string('primary_color', 9)->nullable(),
                    'deleted_at' => fn (Blueprint $t) => $t->softDeletes(),
                ] as $column => $adder) {
                    if (! Schema::hasColumn('tenants', $column)) {
                        $adder($table);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('csp_violations');
    }
};
