<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add proper columns to Discussion module stub tables:
 *  - channels
 *  - messages
 *  - channel_members (create if missing)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── channels ──────────────────────────────────────────────────────────
        if (Schema::hasTable('channels')) {
            Schema::table('channels', function (Blueprint $table) {
                if (!Schema::hasColumn('channels', 'name')) {
                    $table->string('name')->after('id');
                }
                if (!Schema::hasColumn('channels', 'slug')) {
                    $table->string('slug')->nullable();
                }
                if (!Schema::hasColumn('channels', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('channels', 'channel_type')) {
                    $table->string('channel_type')->default('general');
                }
                if (!Schema::hasColumn('channels', 'is_private')) {
                    $table->boolean('is_private')->default(false);
                }
                if (!Schema::hasColumn('channels', 'topic')) {
                    $table->string('topic')->nullable();
                }
                if (!Schema::hasColumn('channels', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('channels', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('channels', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── channel_members ───────────────────────────────────────────────────
        if (false) { // 'channel_members' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('channel_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('role')->default('member');
                $table->timestamps();

                $table->unique(['channel_id', 'user_id']);
            });
        }

        // ── messages ──────────────────────────────────────────────────────────
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (!Schema::hasColumn('messages', 'channel_id')) {
                    $table->unsignedBigInteger('channel_id')->nullable()->index();
                }
                if (!Schema::hasColumn('messages', 'conversation_id')) {
                    $table->unsignedBigInteger('conversation_id')->nullable()->index();
                }
                if (!Schema::hasColumn('messages', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (!Schema::hasColumn('messages', 'body')) {
                    $table->text('body')->nullable();
                }
                if (!Schema::hasColumn('messages', 'content')) {
                    $table->text('content')->nullable();
                }
                if (!Schema::hasColumn('messages', 'type')) {
                    $table->string('type')->default('text');
                }
                if (!Schema::hasColumn('messages', 'is_edited')) {
                    $table->boolean('is_edited')->default(false);
                }
                if (!Schema::hasColumn('messages', 'is_pinned')) {
                    $table->boolean('is_pinned')->default(false);
                }
                if (!Schema::hasColumn('messages', 'edited_at')) {
                    $table->timestamp('edited_at')->nullable();
                }
                if (!Schema::hasColumn('messages', 'edited_by')) {
                    $table->unsignedBigInteger('edited_by')->nullable();
                }
                if (!Schema::hasColumn('messages', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('messages', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('messages', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive — no rollback
    }
};
