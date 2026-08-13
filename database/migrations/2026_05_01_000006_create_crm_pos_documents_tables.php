<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM: Opportunities (depends on crm_pipelines, crm_accounts, crm_contacts, users)
        if (!Schema::hasTable('crm_opportunities')) {
            Schema::create('crm_opportunities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pipeline_id')->nullable()->references('id')->on('crm_pipelines');
                $table->foreignId('account_id')->nullable()->references('id')->on('crm_accounts')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->references('id')->on('crm_contacts')->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->string('name');
                $table->string('stage');
                $table->integer('probability')->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->date('expected_close_date')->nullable();
                $table->string('status')->default('open');
                $table->text('description')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['pipeline_id', 'stage', 'status']);
                $table->index(['owner_id', 'status']);
            });
        }

        // Documents: Root folders
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('folder_id')->nullable()->references('id')->on('documents')->nullOnDelete();
                $table->string('name');
                $table->string('type'); // folder, file
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('path')->nullable();
                $table->foreignId('owner_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->boolean('is_public')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['owner_id', 'type']);
            });
        }

        // Documents: Folders (legacy, might be the same as documents)
        if (!Schema::hasTable('doc_folders')) {
            Schema::create('doc_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->references('id')->on('doc_folders')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('owner_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // POS: Configs
        if (!Schema::hasTable('pos_configs')) {
            Schema::create('pos_configs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // POS: Cash Registers / Sessions
        if (!Schema::hasTable('pos_sessions')) {
            Schema::create('pos_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('config_id')->nullable()->references('id')->on('pos_configs')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->string('status')->default('open');
                $table->decimal('opening_balance', 15, 4)->default(0);
                $table->decimal('closing_balance', 15, 4)->nullable();
                $table->decimal('expected_balance', 15, 4)->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['config_id', 'status']);
            });
        }

        // Ecommerce: Payments
        if (!Schema::hasTable('ec_payments')) {
            Schema::create('ec_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->references('id')->on('ec_orders')->cascadeOnDelete();
                $table->string('payment_method');
                $table->string('transaction_id')->nullable()->unique();
                $table->decimal('amount', 15, 4);
                $table->string('status')->default('pending');
                $table->string('gateway_response')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'status']);
            });
        }

        // Core: Audit Logs (may already exist as audit_logs)
        if (!Schema::hasTable('core_audit_logs')) {
            Schema::create('core_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 100);
                $table->string('model_type', 255)->nullable();
                $table->unsignedBigInteger('model_id')->nullable();
                $table->json('changes')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'action']);
                $table->index(['model_type', 'model_id']);
            });
        }

        // Mobile Sync: Sync Queue
        if (!Schema::hasTable('sync_queue')) {
            Schema::create('sync_queue', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->nullable()->references('id')->on('users')->onDelete('cascade');
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('operation'); // create, update, delete
                $table->json('payload')->nullable();
                $table->string('status')->default('pending');
                $table->integer('retry_count')->default(0);
                $table->timestamp('client_timestamp')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['entity_type', 'entity_id']);
            });
        }

        // Push Notifications: Tokens
        if (!Schema::hasTable('push_tokens')) {
            Schema::create('push_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->string('token')->unique();
                $table->string('platform')->nullable(); // expo, fcm, apns
                $table->string('device_type')->nullable(); // ios, android, web
                $table->string('device_name')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }

        // Rate Limiting: Metrics
        if (!Schema::hasTable('rate_limit_metrics')) {
            Schema::create('rate_limit_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('client_id');
                $table->string('endpoint');
                $table->integer('request_count')->default(0);
                $table->timestamp('window_start');
                $table->timestamp('window_end');
                $table->timestamps();

                $table->unique(['client_id', 'endpoint', 'window_start']);
            });
        }

        // Manufacturing: Bill of Materials
        if (!Schema::hasTable('mfg_bill_of_materials')) {
            Schema::create('mfg_bill_of_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->nullable()->references('id')->on('inventory_products')->nullOnDelete();
                $table->string('version')->default('1.0');
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->decimal('total_cost', 15, 4)->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index('product_id');
            });
        }

        // Manufacturing: BOM Items
        if (!Schema::hasTable('mfg_bom_items')) {
            Schema::create('mfg_bom_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bom_id')->references('id')->on('mfg_bill_of_materials')->onDelete('cascade');
                $table->foreignId('component_id')->nullable()->references('id')->on('inventory_products')->nullOnDelete();
                $table->decimal('quantity', 15, 4);
                $table->string('unit')->nullable();
                $table->decimal('waste_percentage', 5, 2)->default(0);
                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('bom_id');
            });
        }

        // HR: Payroll Records
        if (!Schema::hasTable('hr_payroll_records')) {
            Schema::create('hr_payroll_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->nullable()->references('id')->on('hr_employees')->nullOnDelete();
                $table->date('pay_period_start');
                $table->date('pay_period_end');
                $table->decimal('gross_salary', 15, 2)->default(0);
                $table->decimal('deductions', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);
                $table->string('status')->default('draft');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'pay_period_start']);
            });
        }

        // Inventory: Stock Movements (if not already created)
        if (!Schema::hasTable('inventory_stock_movements')) {
            Schema::create('inventory_stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->nullable()->references('id')->on('inventory_products')->onDelete('cascade');
                $table->foreignId('warehouse_id')->nullable()->references('id')->on('inventory_warehouses')->onDelete('cascade');
                $table->string('type');
                $table->decimal('quantity', 15, 4);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'warehouse_id']);
                $table->index('type');
            });
        }

        // BI: KPIs
        if (!Schema::hasTable('bi_kpis')) {
            Schema::create('bi_kpis', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->string('category');
                $table->string('formula')->nullable();
                $table->decimal('target_value', 15, 4)->nullable();
                $table->string('unit')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('category');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_kpis');
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('hr_payroll_records');
        Schema::dropIfExists('mfg_bom_items');
        Schema::dropIfExists('mfg_bill_of_materials');
        Schema::dropIfExists('rate_limit_metrics');
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('core_audit_logs');
        Schema::dropIfExists('ec_payments');
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('pos_configs');
        Schema::dropIfExists('doc_folders');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('crm_opportunities');
    }
};
