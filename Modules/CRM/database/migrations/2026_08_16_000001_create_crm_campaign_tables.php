<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CampaignController, CampaignOrchestrationService, CampaignPolicy, all 5
 * factories, and CampaignTest.php existed and were fully built, but no
 * migration ever created their tables — every one of them fatals with
 * "table not found". Columns derived from each model's own $fillable/$casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_campaigns')) {
            Schema::create('crm_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type');
                $table->string('status')->default('draft');
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->unsignedInteger('target_count')->default(0);
                $table->unsignedInteger('enrolled_count')->default(0);
                $table->unsignedInteger('converted_count')->default(0);
                $table->decimal('conversion_rate', 5, 2)->default(0);
                $table->dateTime('start_date')->nullable();
                $table->dateTime('end_date')->nullable();
                $table->json('channels')->nullable();
                $table->json('segments')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('crm_campaign_stages')) {
            Schema::create('crm_campaign_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
                $table->string('name');
                $table->unsignedInteger('sequence')->default(0);
                $table->unsignedInteger('delay_days')->default(0);
                $table->string('condition_type')->nullable();
                $table->json('conditions')->nullable();
                $table->json('actions')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_campaign_enrollments')) {
            Schema::create('crm_campaign_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
                $table->string('enrollable_type');
                $table->unsignedBigInteger('enrollable_id');
                $table->string('current_stage')->nullable();
                $table->string('status')->default('active');
                $table->dateTime('enrolled_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->unsignedInteger('email_opens')->default(0);
                $table->unsignedInteger('email_clicks')->default(0);
                $table->unsignedInteger('sms_reads')->default(0);
                $table->unsignedInteger('interactions')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['enrollable_type', 'enrollable_id']);
            });
        }

        if (! Schema::hasTable('crm_campaign_actions')) {
            Schema::create('crm_campaign_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
                $table->foreignId('enrollment_id')->nullable()
                    ->constrained('crm_campaign_enrollments')->cascadeOnDelete();
                $table->string('action_type');
                $table->string('status')->default('pending');
                $table->json('payload')->nullable();
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('executed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_campaign_analytics')) {
            Schema::create('crm_campaign_analytics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
                $table->date('date');
                $table->unsignedInteger('impressions')->default(0);
                $table->unsignedInteger('opens')->default(0);
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('conversions')->default(0);
                $table->decimal('open_rate', 5, 2)->default(0);
                $table->decimal('click_rate', 5, 2)->default(0);
                $table->decimal('conversion_rate', 5, 2)->default(0);
                $table->decimal('revenue_generated', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['campaign_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_analytics');
        Schema::dropIfExists('crm_campaign_actions');
        Schema::dropIfExists('crm_campaign_enrollments');
        Schema::dropIfExists('crm_campaign_stages');
        Schema::dropIfExists('crm_campaigns');
    }
};
