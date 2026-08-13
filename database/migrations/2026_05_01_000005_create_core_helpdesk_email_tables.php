<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Core: Tenants (base table for multi-tenancy)
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name')->nullable();
                $table->timestamps();
                $table->json('data')->nullable();
            });
        }

        // Core: Audit Logs (core_audit_logs)
        if (!Schema::hasTable('core_audit_logs')) {
            Schema::create('core_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->string('user_name', 100)->nullable();
                $table->string('user_role', 50)->nullable();
                $table->string('action', 100)->index();
                $table->string('module', 50)->nullable()->index();
                $table->string('event_type', 50)->nullable()->index();
                $table->string('description', 255)->nullable();
                $table->string('subject_type', 255)->nullable();
                $table->unsignedInteger('subject_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['subject_type', 'subject_id']);
                $table->index('created_at');
            });
        }

        // Core: Data Requests (GDPR)
        if (!Schema::hasTable('core_data_requests')) {
            Schema::create('core_data_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email', 255)->nullable();
                $table->enum('request_type', ['export', 'deletion', 'rectification', 'access']);
                $table->enum('status', ['pending', 'in_progress', 'completed', 'rejected'])->default('pending');
                $table->text('notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->json('data_snapshot')->nullable();
                $table->timestamps();
            });
        }

        // Core: GDPR Consents
        if (!Schema::hasTable('core_gdpr_consents')) {
            Schema::create('core_gdpr_consents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->string('consent_type'); // marketing, analytics, cookies
                $table->boolean('given')->default(false);
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'consent_type']);
            });
        }

        // Helpdesk: KB Categories
        if (!Schema::hasTable('hd_kb_categories')) {
            Schema::create('hd_kb_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->references('id')->on('hd_kb_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_published')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('sort_order');
            });
        }

        // Helpdesk: KB Articles
        if (!Schema::hasTable('hd_kb_articles')) {
            Schema::create('hd_kb_articles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->references('id')->on('hd_kb_categories')->onDelete('cascade');
                $table->unsignedBigInteger('author_id')->nullable();
                $table->string('title');
                $table->string('slug')->unique();
                $table->longText('content')->nullable();
                $table->integer('view_count')->default(0);
                $table->integer('helpful_count')->default(0);
                $table->integer('unhelpful_count')->default(0);
                $table->boolean('is_published')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['category_id', 'is_published']);
            });
        }

        // Helpdesk: Tickets
        if (!Schema::hasTable('hd_tickets')) {
            Schema::create('hd_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_number')->unique();
                $table->foreignId('user_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->string('subject');
                $table->text('description')->nullable();
                $table->string('priority')->default('medium'); // low, medium, high, urgent
                $table->string('status')->default('open'); // open, in_progress, waiting, resolved, closed
                $table->string('category')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['assigned_to', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        // Email: Lists
        if (!Schema::hasTable('email_lists')) {
            Schema::create('email_lists', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->integer('subscriber_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Email: Subscribers
        if (!Schema::hasTable('email_subscribers')) {
            Schema::create('email_subscribers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('list_id')->references('id')->on('email_lists')->onDelete('cascade');
                $table->string('email')->index();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('status')->default('subscribed'); // subscribed, unsubscribed, bounced
                $table->json('tags')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamp('subscribed_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();

                $table->unique(['list_id', 'email']);
            });
        }

        // Email: Campaigns
        if (!Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('list_id')->nullable()->references('id')->on('email_lists')->nullOnDelete();
                $table->string('name');
                $table->string('subject');
                $table->string('from_name')->nullable();
                $table->string('from_email')->nullable();
                $table->string('type')->nullable()->default('regular');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->text('content')->nullable();
                $table->text('html_content')->nullable();
                $table->string('status')->default('draft');
                $table->integer('sent_count')->default(0);
                $table->integer('open_count')->default(0);
                $table->integer('click_count')->default(0);
                $table->integer('total_sent')->default(0);
                $table->integer('total_opened')->default(0);
                $table->integer('total_clicked')->default(0);
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        // WhatsApp: Contacts
        if (!Schema::hasTable('wa_contacts')) {
            Schema::create('wa_contacts', function (Blueprint $table) {
                $table->id();
                $table->string('phone')->unique();
                $table->string('name')->nullable();
                $table->boolean('opted_in')->default(false);
                $table->timestamp('opted_in_at')->nullable();
                $table->timestamps();

                $table->index('opted_in');
            });
        }

        // WhatsApp: Messages
        if (!Schema::hasTable('wa_messages')) {
            Schema::create('wa_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->nullable()->references('id')->on('wa_contacts')->onDelete('cascade');
                $table->string('wa_message_id')->unique()->nullable();
                $table->string('direction'); // inbound, outbound
                $table->text('content')->nullable();
                $table->string('status')->default('sent'); // sent, delivered, read, failed
                $table->timestamps();

                $table->index(['contact_id', 'direction']);
            });
        }

        // WhatsApp: Conversations
        if (!Schema::hasTable('wa_conversations')) {
            Schema::create('wa_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->nullable()->references('id')->on('wa_contacts')->onDelete('cascade');
                $table->foreignId('assignee_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->unsignedBigInteger('last_message_id')->nullable();
                $table->string('status')->default('open'); // open, resolved, archived
                $table->boolean('is_ai_handled')->default(false);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_conversations');
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('wa_contacts');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('email_subscribers');
        Schema::dropIfExists('email_lists');
        Schema::dropIfExists('hd_tickets');
        Schema::dropIfExists('hd_kb_articles');
        Schema::dropIfExists('hd_kb_categories');
        // Core tables (audit logs, data requests, etc.) are dropped in Modules/Core migrations
        // Keep tenants table if multi-tenancy is enabled
        // Schema::dropIfExists('tenants');
    }
};
