<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── acc_consolidation_groups: was a generic stub (id/tenant_id/data/timestamps)
        // later patched with name/description/currency/is_active/consolidation_method.
        // Add the columns ConsolidationService/ConsolidationGroup actually need. ──
        Schema::table('acc_consolidation_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('acc_consolidation_groups', 'parent_company_id')) {
                $table->unsignedBigInteger('parent_company_id')->nullable();
            }
            if (! Schema::hasColumn('acc_consolidation_groups', 'fiscal_year')) {
                $table->integer('fiscal_year')->nullable();
            }
            if (! Schema::hasColumn('acc_consolidation_groups', 'consolidation_date')) {
                $table->date('consolidation_date')->nullable();
            }
            if (! Schema::hasColumn('acc_consolidation_groups', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (! Schema::hasColumn('acc_consolidation_groups', 'status')) {
                $table->string('status', 20)->default('draft');
            }
            if (! Schema::hasColumn('acc_consolidation_groups', 'auto_eliminate_intercompany')) {
                $table->boolean('auto_eliminate_intercompany')->default(false);
            }
        });

        // ── acc_consolidation_members: subsidiary/associate membership of a group,
        // never had a table at all. ──
        if (! Schema::hasTable('acc_consolidation_members')) {
            Schema::create('acc_consolidation_members', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consolidation_group_id');
                $table->unsignedBigInteger('subsidiary_company_id');
                $table->decimal('ownership_percentage', 5, 2)->default(100);
                $table->string('relationship_type', 30)->default('subsidiary');
                $table->date('acquisition_date')->nullable();
                $table->decimal('acquisition_price', 15, 4)->default(0);
                $table->decimal('exchange_rate', 15, 6)->default(1);
                $table->timestamps();

                $table->index('consolidation_group_id');
            });
        }

        // ── acc_consolidation_entries: elimination/adjustment entries posted against
        // a consolidation group. Distinct from the legacy `consolidation_entries`
        // table (no acc_ prefix), which belongs to the separate ConsolidationHierarchy/
        // ConsolidationPeriod stack and is left untouched. ──
        if (! Schema::hasTable('acc_consolidation_entries')) {
            Schema::create('acc_consolidation_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consolidation_group_id');
                $table->string('entry_type', 40);
                $table->unsignedBigInteger('related_transaction_id')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index('consolidation_group_id');
            });
        }

        // ── consolidation_hierarchies: ConsolidationHierarchyPolicy gates update()/
        // delete() on status === 'draft', but the table never had a status column. ──
        if (Schema::hasTable('consolidation_hierarchies') && ! Schema::hasColumn('consolidation_hierarchies', 'status')) {
            Schema::table('consolidation_hierarchies', function (Blueprint $table): void {
                $table->string('status', 20)->default('draft');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_consolidation_entries');
        Schema::dropIfExists('acc_consolidation_members');
    }
};
