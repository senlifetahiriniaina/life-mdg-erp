<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hd_kb_portal_articles started as a generic stub (id, tenant_id, data,
 * timestamps), then a later patch added title/content/category_id/
 * author_id/is_published/views — but Modules\Helpdesk\Models\KbPortalArticle
 * (slug generated in boot(), status/view_count in $fillable) and both
 * KbPortalController and PortalWebController (status/slug/excerpt/
 * view_count filters, selects and increments) have always assumed columns
 * that were never actually added. Every real query against this table —
 * including the public /helpdesk/portal page — has been broken since that
 * patch shipped. This closes the gap rather than add yet another
 * differently-named column on top of the existing drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_kb_portal_articles')) {
            Schema::table('hd_kb_portal_articles', function (Blueprint $table): void {
                if (! Schema::hasColumn('hd_kb_portal_articles', 'slug')) {
                    $table->string('slug')->nullable()->after('title');
                }
                if (! Schema::hasColumn('hd_kb_portal_articles', 'excerpt')) {
                    $table->string('excerpt', 500)->nullable()->after('content');
                }
                if (! Schema::hasColumn('hd_kb_portal_articles', 'status')) {
                    $table->string('status')->default('draft')->after('is_published');
                }
                if (! Schema::hasColumn('hd_kb_portal_articles', 'view_count')) {
                    $table->unsignedInteger('view_count')->default(0)->after('views');
                }
                if (! Schema::hasColumn('hd_kb_portal_articles', 'helpful_count')) {
                    $table->unsignedInteger('helpful_count')->default(0)->after('view_count');
                }
                if (! Schema::hasColumn('hd_kb_portal_articles', 'not_helpful_count')) {
                    $table->unsignedInteger('not_helpful_count')->default(0)->after('helpful_count');
                }
                if (! Schema::hasColumn('hd_kb_portal_articles', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // hd_kb_portal_categories has the same gap: patched with
        // name/description/parent_id/is_active/created_by but never with
        // slug/is_public/sort_order, which KbPortalCategory has always
        // required (its creating() hook unconditionally sets slug) —
        // KbPortalCategory::create() has been broken since that patch shipped.
        if (Schema::hasTable('hd_kb_portal_categories')) {
            Schema::table('hd_kb_portal_categories', function (Blueprint $table): void {
                if (! Schema::hasColumn('hd_kb_portal_categories', 'slug')) {
                    $table->string('slug')->nullable()->after('name');
                }
                if (! Schema::hasColumn('hd_kb_portal_categories', 'is_public')) {
                    $table->boolean('is_public')->default(true)->after('slug');
                }
                if (! Schema::hasColumn('hd_kb_portal_categories', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0)->after('is_public');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hd_kb_portal_articles')) {
            Schema::table('hd_kb_portal_articles', function (Blueprint $table): void {
                $table->dropColumn(['slug', 'excerpt', 'status', 'view_count', 'helpful_count', 'not_helpful_count', 'deleted_at']);
            });
        }

        if (Schema::hasTable('hd_kb_portal_categories')) {
            Schema::table('hd_kb_portal_categories', function (Blueprint $table): void {
                $table->dropColumn(['slug', 'is_public', 'sort_order']);
            });
        }
    }
};
