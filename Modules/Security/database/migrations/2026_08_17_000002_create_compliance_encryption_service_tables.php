<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('compliance_audits')) {
            Schema::create('compliance_audits', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 36)->index();
                $table->string('audit_type', 32);
                $table->string('framework', 32);
                $table->timestamp('audit_start_date')->nullable();
                $table->timestamp('audit_end_date')->nullable();
                $table->unsignedInteger('controls_evaluated')->default(0);
                $table->unsignedInteger('controls_compliant')->default(0);
                $table->unsignedInteger('controls_non_compliant')->default(0);
                $table->decimal('compliance_score', 5, 2)->nullable();
                $table->json('findings')->nullable();
                $table->string('audit_status', 16)->default('in_progress')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('key_rotation_logs')) {
            Schema::create('key_rotation_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('encryption_key_id')->constrained('security_encryption_keys')->cascadeOnDelete();
                $table->string('rotation_type', 32);
                $table->string('rotation_status', 16)->default('in_progress')->index();
                $table->string('old_key_hash', 128)->nullable();
                $table->string('new_key_hash', 128)->nullable();
                $table->unsignedInteger('records_reencrypted')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('encrypted_fields')) {
            Schema::create('encrypted_fields', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 36)->index();
                $table->string('table_name', 128);
                $table->string('column_name', 128);
                $table->string('encryption_algorithm', 32);
                $table->foreignId('encryption_key_id')->nullable()->constrained('security_encryption_keys')->nullOnDelete();
                $table->boolean('is_searchable')->default(false);
                $table->boolean('is_encrypted')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('service_identities')) {
            Schema::create('service_identities', function (Blueprint $table) {
                $table->id();
                $table->string('company_id', 36)->index();
                $table->string('service_name', 128);
                $table->string('service_type', 32);
                $table->text('public_key')->nullable();
                $table->string('private_key_hash', 128)->nullable();
                $table->json('allowed_permissions')->nullable();
                $table->json('resource_restrictions')->nullable();
                $table->timestamp('last_rotated_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_identities');
        Schema::dropIfExists('encrypted_fields');
        Schema::dropIfExists('key_rotation_logs');
        Schema::dropIfExists('compliance_audits');
    }
};
