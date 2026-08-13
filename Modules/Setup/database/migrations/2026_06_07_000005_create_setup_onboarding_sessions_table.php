<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('setup_onboarding_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->string('current_step', 32)->default('company')->index();
            $table->unsignedInteger('total_duration_seconds')->default(0);
            $table->string('source_type', 32)->default('web');
            $table->unsignedInteger('rows_imported')->default(0);
            $table->boolean('ai_mapping_used')->default(false);
            $table->decimal('ai_mapping_accepted_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('errors_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_onboarding_sessions');
    }
};
