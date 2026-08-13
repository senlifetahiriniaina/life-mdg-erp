<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old incompatible schema and recreate with correct columns
        Schema::dropIfExists('rate_limit_metrics');

        Schema::create('rate_limit_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('endpoint');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('status_code')->default(200);
            $table->integer('response_time_ms')->nullable();
            $table->timestamp('timestamp');
            $table->string('tenant_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['endpoint', 'timestamp']);
            $table->index('user_id');
            $table->index('tenant_id');
        });

        // Add missing columns to core_approval_instances
        if (Schema::hasTable('core_approval_instances')) {
            Schema::table('core_approval_instances', function (Blueprint $table) {
                if (!Schema::hasColumn('core_approval_instances', 'escalated_to')) {
                    $table->unsignedBigInteger('escalated_to')->nullable();
                }
                if (!Schema::hasColumn('core_approval_instances', 'escalation_reason')) {
                    $table->text('escalation_reason')->nullable();
                }
                if (!Schema::hasColumn('core_approval_instances', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                }
            });
        }

        // Create GDPR-related tables needed by tests
        if (!Schema::hasTable('gdpr_sar_requests')) {
            Schema::create('gdpr_sar_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('email')->nullable();
                $table->string('format')->default('json');
                $table->enum('status', ['pending', 'confirmed', 'processing', 'completed', 'rejected'])->default('pending');
                $table->string('confirmation_token')->nullable()->unique();
                $table->timestamp('confirmed_at')->nullable();
                $table->string('tenant_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('gdpr_audit_logs')) {
            Schema::create('gdpr_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('action');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('timestamp')->nullable();
                $table->boolean('immutable')->default(false);
                $table->string('tenant_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });

            // Add trigger to prevent updates on immutable rows (SQLite compatible)
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('CREATE TRIGGER prevent_immutable_gdpr_audit_update
                    BEFORE UPDATE ON gdpr_audit_logs
                    WHEN OLD.immutable = 1
                    BEGIN
                        SELECT RAISE(IGNORE);
                    END;');
            }
        }

        if (!Schema::hasTable('gdpr_consents')) {
            Schema::create('gdpr_consents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->boolean('analytics')->default(false);
                $table->boolean('marketing')->default(false);
                $table->boolean('functional')->default(true);
                $table->string('tenant_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('analytics_events')) {
            Schema::create('analytics_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event_type')->nullable();
                $table->json('properties')->nullable();
                $table->timestamps();
            });
        }

        // Add missing columns to acc_expense_reports, make title nullable
        if (Schema::hasTable('acc_expense_reports')) {
            // SQLite doesn't support modifying column constraints, so we recreate
            // Just add the missing columns
            Schema::table('acc_expense_reports', function (Blueprint $table) {
                if (!Schema::hasColumn('acc_expense_reports', 'period_start')) {
                    $table->date('period_start')->nullable();
                }
                if (!Schema::hasColumn('acc_expense_reports', 'period_end')) {
                    $table->date('period_end')->nullable();
                }
                if (!Schema::hasColumn('acc_expense_reports', 'total_amount')) {
                    $table->decimal('total_amount', 15, 4)->default(0);
                }
                if (!Schema::hasColumn('acc_expense_reports', 'submitted_by_id')) {
                    $table->unsignedBigInteger('submitted_by_id')->nullable();
                }
            });
        }

        // Add missing columns to acc_journal_entries
        if (Schema::hasTable('acc_journal_entries')) {
            Schema::table('acc_journal_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('acc_journal_entries', 'contract_id')) {
                    $table->unsignedBigInteger('contract_id')->nullable()->index();
                }
                if (!Schema::hasColumn('acc_journal_entries', 'date')) {
                    $table->date('date')->nullable()->index();
                }
                if (!Schema::hasColumn('acc_journal_entries', 'fiscal_year_id')) {
                    $table->unsignedBigInteger('fiscal_year_id')->nullable();
                }
                if (!Schema::hasColumn('acc_journal_entries', 'debit_amount')) {
                    $table->decimal('debit_amount', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('acc_journal_entries', 'credit_amount')) {
                    $table->decimal('credit_amount', 15, 4)->nullable();
                }
                if (!Schema::hasColumn('acc_journal_entries', 'source_type')) {
                    $table->string('source_type')->nullable();
                }
                if (!Schema::hasColumn('acc_journal_entries', 'journal_id')) {
                    $table->unsignedBigInteger('journal_id')->nullable();
                }
            });
        }

        // Add missing columns to acc_gl_entries
        if (Schema::hasTable('acc_gl_entries')) {
            Schema::table('acc_gl_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('acc_gl_entries', 'journal_id')) {
                    $table->unsignedBigInteger('journal_id')->nullable()->index();
                }
                if (!Schema::hasColumn('acc_gl_entries', 'line_number')) {
                    $table->integer('line_number')->nullable();
                }
            });
        }

        // Add missing columns to acc_gl_journals
        if (Schema::hasTable('acc_gl_journals')) {
            Schema::table('acc_gl_journals', function (Blueprint $table) {
                if (!Schema::hasColumn('acc_gl_journals', 'journal_type')) {
                    $table->string('journal_type')->nullable();
                }
                if (!Schema::hasColumn('acc_gl_journals', 'posted_date')) {
                    $table->date('posted_date')->nullable();
                }
                if (!Schema::hasColumn('acc_gl_journals', 'created_by_id')) {
                    $table->unsignedBigInteger('created_by_id')->nullable();
                }
                if (!Schema::hasColumn('acc_gl_journals', 'number')) {
                    $table->string('number')->nullable();
                }
                if (!Schema::hasColumn('acc_gl_journals', 'date')) {
                    $table->date('date')->nullable();
                }
                if (!Schema::hasColumn('acc_gl_journals', 'reference_type')) {
                    $table->string('reference_type')->nullable();
                }
                if (!Schema::hasColumn('acc_gl_journals', 'reference_id')) {
                    $table->unsignedBigInteger('reference_id')->nullable();
                }
            });
        }

        // Add missing columns to acc_intercompany_rules
        if (Schema::hasTable('acc_intercompany_rules')) {
            Schema::table('acc_intercompany_rules', function (Blueprint $table) {
                if (!Schema::hasColumn('acc_intercompany_rules', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'name')) {
                    $table->string('name')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'transaction_type')) {
                    $table->string('transaction_type')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'source_entity_id')) {
                    $table->unsignedBigInteger('source_entity_id')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'target_entity_id')) {
                    $table->unsignedBigInteger('target_entity_id')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'source_gl_account_id')) {
                    $table->unsignedBigInteger('source_gl_account_id')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'target_gl_account_id')) {
                    $table->unsignedBigInteger('target_gl_account_id')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'auto_post')) {
                    $table->boolean('auto_post')->default(false);
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'posting_frequency')) {
                    $table->string('posting_frequency')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'require_approval')) {
                    $table->boolean('require_approval')->default(true);
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'conditions')) {
                    $table->json('conditions')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'created_by_id')) {
                    $table->unsignedBigInteger('created_by_id')->nullable();
                }
                if (!Schema::hasColumn('acc_intercompany_rules', 'updated_by_id')) {
                    $table->unsignedBigInteger('updated_by_id')->nullable();
                }
            });
        }

        // Add missing columns to acc_expenses
        if (Schema::hasTable('acc_expenses')) {
            Schema::table('acc_expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('acc_expenses', 'expense_reference')) {
                    $table->string('expense_reference')->nullable()->unique();
                }
                if (!Schema::hasColumn('acc_expenses', 'category')) {
                    $table->string('category')->nullable();
                }
                if (!Schema::hasColumn('acc_expenses', 'vendor')) {
                    $table->string('vendor')->nullable();
                }
                if (!Schema::hasColumn('acc_expenses', 'receipt_number')) {
                    $table->string('receipt_number')->nullable();
                }
                if (!Schema::hasColumn('acc_expenses', 'approved_by_id')) {
                    $table->unsignedBigInteger('approved_by_id')->nullable();
                }
                if (!Schema::hasColumn('acc_expenses', 'created_by_id')) {
                    $table->unsignedBigInteger('created_by_id')->nullable();
                }
                if (!Schema::hasColumn('acc_expenses', 'payment_method')) {
                    $table->string('payment_method')->nullable();
                }
                if (!Schema::hasColumn('acc_expenses', 'created_by')) {
                    $table->string('created_by')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('gdpr_consents');
        Schema::dropIfExists('gdpr_audit_logs');
        Schema::dropIfExists('gdpr_sar_requests');
        Schema::dropIfExists('rate_limit_metrics');
    }
};
