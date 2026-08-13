<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM: Territories
        if (!Schema::hasTable('crm_territories')) {
            Schema::create('crm_territories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_territory_id')
                    ->nullable()
                    ->constrained('crm_territories')
                    ->nullOnDelete();
                $table->foreignId('assigned_to')
                    ->constrained('users')
                    ->onDelete('cascade');
                $table->string('name');
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->string('region', 100)->nullable();
                $table->decimal('sales_target', 15, 2);
                $table->string('currency', 3)->default('USD');
                $table->boolean('is_active')->default(true);
                $table->date('year_start_date')->nullable();
                $table->timestamps();

                $table->index(['assigned_to', 'is_active']);
                $table->index('parent_territory_id');
            });
        }

        // CRM: Territory Assignments
        if (!Schema::hasTable('crm_territory_assignments')) {
            Schema::create('crm_territory_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('territory_id')
                    ->constrained('crm_territories')
                    ->onDelete('cascade');
                $table->foreignId('contact_id')
                    ->nullable()
                    ->constrained('crm_contacts')
                    ->nullOnDelete();
                $table->foreignId('account_id')
                    ->nullable()
                    ->constrained('crm_accounts')
                    ->nullOnDelete();
                $table->boolean('auto_assigned')->default(false);
                $table->text('assignment_notes')->nullable();
                $table->timestamps();

                $table->unique(['territory_id', 'contact_id', 'account_id'], 'crm_territory_assign_unique');
            });
        }

        // CRM: Activities
        if (!Schema::hasTable('crm_activities')) {
            Schema::create('crm_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')
                    ->nullable()
                    ->constrained('crm_contacts')
                    ->nullOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->onDelete('cascade');
                $table->string('type'); // call, email, meeting, note, etc.
                $table->string('subject')->nullable();
                $table->text('description')->nullable();
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['contact_id', 'type']);
                $table->index(['user_id', 'scheduled_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_territory_assignments');
        Schema::dropIfExists('crm_territories');
    }
};
