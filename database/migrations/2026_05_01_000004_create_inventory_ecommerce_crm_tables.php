<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inventory: Categories
        if (!Schema::hasTable('inventory_categories')) {
            Schema::create('inventory_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->references('id')->on('inventory_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Inventory: Units
        if (!Schema::hasTable('inventory_units')) {
            Schema::create('inventory_units', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('symbol', 20);
                $table->string('type')->default('unit');
                $table->timestamps();
            });
        }

        // Inventory: Products
        if (!Schema::hasTable('inventory_products')) {
            Schema::create('inventory_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->nullable()->references('id')->on('inventory_categories')->nullOnDelete();
                $table->foreignId('unit_id')->nullable()->references('id')->on('inventory_units')->nullOnDelete();
                $table->string('name');
                $table->string('sku')->unique();
                $table->string('barcode')->nullable();
                $table->text('description')->nullable();
                $table->string('type')->default('storable');
                $table->decimal('cost_price', 15, 4)->default(0);
                $table->decimal('sale_price', 15, 4)->default(0);
                $table->decimal('selling_price', 15, 4)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('category')->nullable();
                $table->string('unit')->nullable();
                $table->string('status')->default('active');
                $table->integer('reorder_level')->default(0);
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->integer('reorder_point')->default(0);
                $table->integer('reorder_qty')->default(0);
                $table->string('valuation_method')->default('average');
                $table->boolean('track_serial')->default(false);
                $table->boolean('track_lot')->default(false);
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('attributes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['category_id', 'is_active']);
                $table->index('sku');
                $table->index('barcode');
            });
        }

        // Inventory: Warehouses
        if (!Schema::hasTable('inventory_warehouses')) {
            Schema::create('inventory_warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('type')->default('main');
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country', 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Inventory: Locations
        if (!Schema::hasTable('inventory_locations')) {
            Schema::create('inventory_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->references('id')->on('inventory_warehouses')->onDelete('cascade');
                $table->string('name');
                $table->string('code')->nullable();
                $table->timestamps();

                $table->unique(['warehouse_id', 'code']);
            });
        }

        // Inventory: Stock
        if (!Schema::hasTable('inventory_stock')) {
            Schema::create('inventory_stock', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->references('id')->on('inventory_products')->onDelete('cascade');
                $table->foreignId('warehouse_id')->references('id')->on('inventory_warehouses')->onDelete('cascade');
                $table->foreignId('location_id')->nullable()->references('id')->on('inventory_locations')->nullOnDelete();
                $table->decimal('quantity', 15, 4)->default(0);
                $table->decimal('reserved_quantity', 15, 4)->default(0);
                $table->decimal('avg_cost', 15, 4)->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'warehouse_id', 'location_id']);
                $table->index(['product_id', 'warehouse_id']);
            });
        }

        // Inventory: Movements
        if (!Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->references('id')->on('inventory_products');
                $table->foreignId('warehouse_id')->references('id')->on('inventory_warehouses');
                $table->foreignId('location_id')->nullable()->references('id')->on('inventory_locations')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->references('id')->on('users')->onDelete('cascade');
                $table->string('reference')->nullable();
                $table->string('type');
                $table->decimal('quantity', 15, 4);
                $table->decimal('unit_cost', 15, 4)->default(0);
                $table->string('lot_number')->nullable();
                $table->string('serial_number')->nullable();
                $table->date('expiry_date')->nullable();
                $table->text('notes')->nullable();
                $table->morphs('source');
                $table->timestamps();

                $table->index(['product_id', 'type', 'created_at']);
                $table->index(['warehouse_id', 'created_at']);
            });
        }

        // CRM: Pipelines
        if (!Schema::hasTable('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_default')->default(false);
                $table->json('stages');
                $table->timestamps();
            });
        }

        // Ecommerce: Stores
        if (!Schema::hasTable('ec_stores')) {
            Schema::create('ec_stores', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('domain')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->string('logo')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Ecommerce: Categories
        if (!Schema::hasTable('ec_categories')) {
            Schema::create('ec_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->references('id')->on('ec_stores')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->references('id')->on('ec_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Ecommerce: Products
        if (!Schema::hasTable('ec_products')) {
            Schema::create('ec_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->references('id')->on('ec_stores')->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->references('id')->on('ec_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->decimal('price', 15, 4);
                $table->decimal('compare_price', 15, 4)->nullable();
                $table->string('sku')->nullable();
                $table->integer('stock_quantity')->default(0);
                $table->boolean('track_inventory')->default(true);
                $table->json('images')->nullable();
                $table->json('attributes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('seo')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Ecommerce: Orders
        if (!Schema::hasTable('ec_orders')) {
            Schema::create('ec_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->references('id')->on('ec_stores')->cascadeOnDelete();
                $table->string('order_number')->unique();
                $table->foreignId('user_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->string('payment_status')->default('pending');
                $table->string('fulfillment_status')->default('unfulfilled');
                $table->decimal('subtotal', 15, 4)->default(0);
                $table->decimal('tax_total', 15, 4)->default(0);
                $table->decimal('shipping_total', 15, 4)->default(0);
                $table->decimal('discount_total', 15, 4)->default(0);
                $table->decimal('grand_total', 15, 4)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->json('billing_address')->nullable();
                $table->json('shipping_address')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('payment_reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Ecommerce: Order Items
        if (!Schema::hasTable('ec_order_items')) {
            Schema::create('ec_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->references('id')->on('ec_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->references('id')->on('ec_products')->nullOnDelete();
                $table->string('product_name');
                $table->string('sku')->nullable();
                $table->integer('quantity');
                $table->decimal('unit_price', 15, 4);
                $table->decimal('total_price', 15, 4);
                $table->json('attributes')->nullable();
                $table->timestamps();
            });
        }

        // Ecommerce: Coupons
        if (!Schema::hasTable('ec_coupons')) {
            Schema::create('ec_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->nullable()->references('id')->on('ec_stores')->nullOnDelete();
                $table->string('code')->unique();
                $table->string('type')->default('fixed');
                $table->decimal('value', 15, 4);
                $table->decimal('minimum_amount', 15, 4)->nullable();
                $table->integer('usage_limit')->nullable();
                $table->integer('used_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Projects: Projects
        if (!Schema::hasTable('prj_projects')) {
            Schema::create('prj_projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->nullable()->references('id')->on('users');
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('budget', 15, 2)->nullable();
                $table->string('currency', 3)->default('USD');
                $table->string('color', 7)->default('#3B82F6');
                $table->boolean('is_billable')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'owner_id']);
            });
        }

        // Projects: Milestones
        if (!Schema::hasTable('prj_milestones')) {
            Schema::create('prj_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->references('id')->on('prj_projects')->onDelete('cascade');
                $table->string('name');
                $table->date('due_date')->nullable();
                $table->boolean('is_reached')->default(false);
                $table->timestamp('reached_at')->nullable();
                $table->timestamps();
            });
        }

        // Projects: Tasks
        if (!Schema::hasTable('prj_tasks')) {
            Schema::create('prj_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->references('id')->on('prj_projects')->onDelete('cascade');
                $table->foreignId('milestone_id')->nullable()->references('id')->on('prj_milestones')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->references('id')->on('prj_tasks')->nullOnDelete();
                $table->foreignId('assignee_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->references('id')->on('users');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('todo');
                $table->string('priority')->default('medium');
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->integer('estimated_hours')->default(0);
                $table->integer('logged_hours')->default(0);
                $table->integer('sequence')->default(0);
                $table->json('tags')->nullable();
                $table->json('dependencies')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['project_id', 'status', 'assignee_id']);
                $table->index(['assignee_id', 'due_date']);
            });
        }

        // Projects: Time Logs
        if (!Schema::hasTable('prj_time_logs')) {
            Schema::create('prj_time_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->references('id')->on('prj_projects')->onDelete('cascade');
                $table->foreignId('task_id')->nullable()->references('id')->on('prj_tasks')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->references('id')->on('users')->onDelete('cascade');
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->text('description')->nullable();
                $table->boolean('billable')->default(false);
                $table->decimal('hourly_rate', 10, 2)->nullable();
                $table->timestamps();

                $table->index(['project_id', 'user_id']);
                $table->index(['user_id', 'started_at']);
            });
        }

        // Projects: Members
        if (!Schema::hasTable('prj_members')) {
            Schema::create('prj_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->references('id')->on('prj_projects')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->references('id')->on('users')->onDelete('cascade');
                $table->string('role')->default('member');
                $table->timestamps();

                $table->unique(['project_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_members');
        Schema::dropIfExists('prj_time_logs');
        Schema::dropIfExists('prj_tasks');
        Schema::dropIfExists('prj_milestones');
        Schema::dropIfExists('prj_projects');
        Schema::dropIfExists('ec_coupons');
        Schema::dropIfExists('ec_order_items');
        Schema::dropIfExists('ec_orders');
        Schema::dropIfExists('ec_products');
        Schema::dropIfExists('ec_categories');
        Schema::dropIfExists('ec_stores');
        Schema::dropIfExists('crm_pipelines');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stock');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('inventory_warehouses');
        Schema::dropIfExists('inventory_products');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_categories');
    }
};
