<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_contacts')) {
            // Create accounts first (dependency)
            if (!Schema::hasTable('crm_accounts')) {
                Schema::create('crm_accounts', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('owner_id')->nullable()->references('id')->on('users')->nullOnDelete();
                    $table->string('name');
                    $table->string('type')->default('prospect');
                    $table->string('industry')->nullable();
                    $table->string('website')->nullable();
                    $table->string('phone')->nullable();
                    $table->string('email')->nullable();
                    $table->integer('employee_count')->nullable();
                    $table->decimal('annual_revenue', 15, 2)->nullable();
                    $table->string('currency', 3)->default('USD');
                    $table->string('billing_address')->nullable();
                    $table->string('billing_city')->nullable();
                    $table->string('billing_country', 2)->nullable();
                    $table->text('description')->nullable();
                    $table->json('custom_fields')->nullable();
                    $table->timestamps();
                    $table->softDeletes();

                    $table->index(['owner_id', 'type']);
                });
            }

            Schema::create('crm_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->nullable()->references('id')->on('crm_accounts')->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->references('id')->on('users')->nullOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('mobile')->nullable();
                $table->string('job_title')->nullable();
                $table->string('department')->nullable();
                $table->string('linkedin_url')->nullable();
                $table->string('source')->nullable();
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['owner_id', 'status']);
                $table->index('email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_accounts');
    }
};
