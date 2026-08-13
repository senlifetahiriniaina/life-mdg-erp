<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_forums', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('helpdesk_forum_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_id')->constrained('helpdesk_forums')->cascadeOnDelete();
            $table->unsignedBigInteger('author_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->enum('status', ['open', 'closed', 'pinned'])->default('open');
            $table->unsignedBigInteger('views')->default(0);
            $table->boolean('is_answered')->default(false);
            $table->timestamps();

            $table->index(['forum_id', 'status']);
            $table->index('author_id');
        });

        Schema::create('helpdesk_forum_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('helpdesk_forum_threads')->cascadeOnDelete();
            $table->unsignedBigInteger('author_id');
            $table->longText('content');
            $table->boolean('is_accepted_answer')->default(false);
            $table->integer('upvotes')->default(0);
            $table->timestamps();

            $table->index(['thread_id', 'is_accepted_answer']);
            $table->index('author_id');
        });

        Schema::create('helpdesk_forum_votes', function (Blueprint $table) {
            $table->id();
            $table->morphs('votable'); // votable_type, votable_id
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('vote'); // 1 or -1
            $table->timestamps();

            $table->unique(['votable_type', 'votable_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_forum_votes');
        Schema::dropIfExists('helpdesk_forum_replies');
        Schema::dropIfExists('helpdesk_forum_threads');
        Schema::dropIfExists('helpdesk_forums');
    }
};
