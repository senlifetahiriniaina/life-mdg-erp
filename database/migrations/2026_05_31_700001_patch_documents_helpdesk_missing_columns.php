<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // ── document_workspace_members ────────────────────────────────────
        $this->patch('document_workspace_members', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'workspace_id'))
                $t->unsignedBigInteger('workspace_id')->nullable();
            if (! Schema::hasColumn($table, 'user_id'))
                $t->unsignedBigInteger('user_id')->nullable();
            if (! Schema::hasColumn($table, 'role'))
                $t->string('role')->default('viewer');
            if (! Schema::hasColumn($table, 'permissions'))
                $t->json('permissions')->nullable();
            if (! Schema::hasColumn($table, 'joined_at'))
                $t->timestamp('joined_at')->nullable();
            if (! Schema::hasColumn($table, 'invited_by'))
                $t->unsignedBigInteger('invited_by')->nullable();
        });

        // ── document_workspaces ───────────────────────────────────────────
        $this->patch('document_workspaces', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'name'))
                $t->string('name')->nullable();
            if (! Schema::hasColumn($table, 'description'))
                $t->text('description')->nullable();
            if (! Schema::hasColumn($table, 'type'))
                $t->string('type')->default('private');
            if (! Schema::hasColumn($table, 'owner_id'))
                $t->unsignedBigInteger('owner_id')->nullable();
            if (! Schema::hasColumn($table, 'is_active'))
                $t->boolean('is_active')->default(true);
            if (! Schema::hasColumn($table, 'settings'))
                $t->json('settings')->nullable();
        });

        // ── doc_signature_signers: add missing columns ────────────────────
        $this->patch('doc_signature_signers', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'ip_address'))
                $t->string('ip_address')->nullable();
            if (! Schema::hasColumn($table, 'user_agent'))
                $t->text('user_agent')->nullable();
            if (! Schema::hasColumn($table, 'signed_at'))
                $t->timestamp('signed_at')->nullable();
            if (! Schema::hasColumn($table, 'certificate_data'))
                $t->json('certificate_data')->nullable();
            if (! Schema::hasColumn($table, 'signature_image'))
                $t->text('signature_image')->nullable();
        });

        // ── doc_esignature_requests: add missing columns ──────────────────
        $this->patch('doc_esignature_requests', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'document_id'))
                $t->unsignedBigInteger('document_id')->nullable();
            if (! Schema::hasColumn($table, 'requester_id'))
                $t->unsignedBigInteger('requester_id')->nullable();
            if (! Schema::hasColumn($table, 'message'))
                $t->text('message')->nullable();
            if (! Schema::hasColumn($table, 'due_date'))
                $t->date('due_date')->nullable();
            if (! Schema::hasColumn($table, 'completed_at'))
                $t->timestamp('completed_at')->nullable();
        });

        // ── hd_kb_article_views: add missing columns ──────────────────────
        $this->patch('hd_kb_article_views', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'article_id'))
                $t->unsignedBigInteger('article_id')->nullable();
            if (! Schema::hasColumn($table, 'user_id'))
                $t->unsignedBigInteger('user_id')->nullable();
            if (! Schema::hasColumn($table, 'ip_address'))
                $t->string('ip_address')->nullable();
            if (! Schema::hasColumn($table, 'viewed_at'))
                $t->timestamp('viewed_at')->nullable();
            if (! Schema::hasColumn($table, 'helpful'))
                $t->boolean('helpful')->nullable();
        });

        // ── hd_escalation_events: add missing columns ────────────────────────
        $this->patch('hd_escalation_events', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'ticket_id'))
                $t->unsignedBigInteger('ticket_id')->nullable();
            if (! Schema::hasColumn($table, 'rule_id'))
                $t->unsignedBigInteger('rule_id')->nullable();
            if (! Schema::hasColumn($table, 'triggered_at'))
                $t->timestamp('triggered_at')->nullable();
            if (! Schema::hasColumn($table, 'action_taken'))
                $t->string('action_taken')->nullable();
            if (! Schema::hasColumn($table, 'result'))
                $t->string('result')->nullable();
        });

        // ── hd_chat_sessions: add missing columns ─────────────────────────
        $this->patch('hd_chat_sessions', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'closed_at'))
                $t->timestamp('closed_at')->nullable();
            if (! Schema::hasColumn($table, 'ticket_id'))
                $t->unsignedBigInteger('ticket_id')->nullable();
            if (! Schema::hasColumn($table, 'agent_id'))
                $t->unsignedBigInteger('agent_id')->nullable();
            if (! Schema::hasColumn($table, 'channel'))
                $t->string('channel')->default('web');
            if (! Schema::hasColumn($table, 'metadata'))
                $t->json('metadata')->nullable();
        });

        // ── hd_tickets: make ticket_number nullable (booted hook generates it) ──
        // SQLite doesn't support altering column nullability; recreate not needed
        // since we patch via a separate migration if column exists as NOT NULL.
        // Instead, ensure ticket_number has a default at the application level.

        // ── doc_approval_instances: add missing columns ───────────────────
        $this->patch('doc_approval_instances', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'completed_at'))
                $t->timestamp('completed_at')->nullable();
            if (! Schema::hasColumn($table, 'document_id'))
                $t->unsignedBigInteger('document_id')->nullable();
            if (! Schema::hasColumn($table, 'workflow_id'))
                $t->unsignedBigInteger('workflow_id')->nullable();
            if (! Schema::hasColumn($table, 'current_step'))
                $t->integer('current_step')->default(0);
        });

        // ── hd_kb_articles: add missing columns ───────────────────────────
        $this->patch('hd_kb_articles', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'category_id'))
                $t->unsignedBigInteger('category_id')->nullable();
            if (! Schema::hasColumn($table, 'author_id'))
                $t->unsignedBigInteger('author_id')->nullable();
            if (! Schema::hasColumn($table, 'title'))
                $t->string('title')->nullable();
            if (! Schema::hasColumn($table, 'content'))
                $t->longText('content')->nullable();
            if (! Schema::hasColumn($table, 'excerpt'))
                $t->text('excerpt')->nullable();
            if (! Schema::hasColumn($table, 'slug'))
                $t->string('slug')->nullable();
            if (! Schema::hasColumn($table, 'is_published'))
                $t->boolean('is_published')->default(false);
            if (! Schema::hasColumn($table, 'views_count'))
                $t->integer('views_count')->default(0);
            if (! Schema::hasColumn($table, 'helpful_count'))
                $t->integer('helpful_count')->default(0);
            if (! Schema::hasColumn($table, 'not_helpful_count'))
                $t->integer('not_helpful_count')->default(0);
            if (! Schema::hasColumn($table, 'published_at'))
                $t->timestamp('published_at')->nullable();
            if (! Schema::hasColumn($table, 'archived_at'))
                $t->timestamp('archived_at')->nullable();
            if (! Schema::hasColumn($table, 'tags'))
                $t->json('tags')->nullable();
            if (! Schema::hasColumn($table, 'updated_by'))
                $t->unsignedBigInteger('updated_by')->nullable();
            if (! Schema::hasColumn($table, 'reviewed_by'))
                $t->unsignedBigInteger('reviewed_by')->nullable();
        });
    }

    public function down(): void {}
};
