<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('crm_workflows')) {
            Schema::create('crm_workflows', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('trigger_type', 64);
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->string('status', 20)->default('draft');
                $table->json('trigger_config')->nullable();
                $table->unsignedInteger('execution_count')->default(0);
                $table->unsignedInteger('success_count')->default(0);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_revenue_insights')) {
            Schema::create('crm_revenue_insights', function (Blueprint $table) {
                $table->id();
                $table->string('insight_type', 32);
                $table->string('category', 64);
                $table->string('title');
                $table->text('description')->nullable();
                $table->json('data')->nullable();
                $table->unsignedTinyInteger('impact_score')->default(1);
                $table->string('status', 20)->default('active');
                $table->unsignedBigInteger('relevant_user_id')->nullable();
                $table->timestamp('insight_generated_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_customers')) {
            Schema::create('crm_customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('company')->nullable();
                $table->string('status', 20)->default('active');
                $table->string('tier', 20)->default('bronze');
                $table->decimal('lifetime_value', 15, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('email');
                $table->index('tier');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customers');
        Schema::dropIfExists('crm_revenue_insights');
        Schema::dropIfExists('crm_workflows');
    }
};
