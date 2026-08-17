<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.2 (BI — DataStory subsystem): DataStoryController (15 methods —
 * data storytelling: create/edit/publish/share slides, narrative flows, view
 * analytics) and DataStoryPolicy are fully written and call into 5 real
 * models (DataStory, StorySlide, NarrativeFlow, StoryAnalytics, StoryView)
 * with correct $fillable/$casts, but none of the 5 tables were ever created
 * — the controller was unreachable end-to-end. Creates all 5 now, matching
 * every other model-with-no-table gap already fixed elsewhere in this
 * codebase (see Helpdesk's 2026_08_23_000001_create_remaining_cs_ai_tables).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('bi_data_stories')) {
            Schema::create('bi_data_stories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('summary')->nullable();
                $table->string('status', 32)->default('draft')->index();
                $table->unsignedInteger('slide_count')->default(0);
                $table->boolean('is_public')->default(false)->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_story_slides')) {
            Schema::create('bi_story_slides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('story_id')->index();
                $table->unsignedInteger('slide_number');
                $table->string('title');
                $table->longText('narrative_text');
                $table->json('visualization_config')->nullable();
                $table->json('interaction_rules')->nullable();
                $table->string('transition_type', 32)->default('fade');
                $table->unsignedInteger('transition_duration')->default(300);
                $table->json('layout')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_narrative_flows')) {
            Schema::create('bi_narrative_flows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('story_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->json('flow_config');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_story_analytics')) {
            Schema::create('bi_story_analytics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('story_id')->index();
                $table->unsignedInteger('total_views')->default(0);
                $table->unsignedInteger('unique_viewers')->default(0);
                $table->unsignedInteger('total_slide_views')->default(0);
                $table->decimal('avg_time_per_slide', 10, 2)->default(0);
                $table->decimal('completion_rate', 5, 2)->default(0);
                $table->unsignedInteger('shares_count')->default(0);
                $table->unsignedInteger('interactions_count')->default(0);
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_story_views')) {
            Schema::create('bi_story_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('story_id')->index();
                $table->unsignedBigInteger('viewer_id')->nullable()->index();
                $table->unsignedInteger('slide_count_viewed')->default(0);
                $table->decimal('time_spent_seconds', 10, 2)->default(0);
                $table->string('source', 64)->nullable();
                $table->json('interaction_log')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_story_views');
        Schema::dropIfExists('bi_story_analytics');
        Schema::dropIfExists('bi_narrative_flows');
        Schema::dropIfExists('bi_story_slides');
        Schema::dropIfExists('bi_data_stories');
    }
};
