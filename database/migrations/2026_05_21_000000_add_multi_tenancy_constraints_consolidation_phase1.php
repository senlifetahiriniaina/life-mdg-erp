<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add multi-tenancy constraints for consolidation Phase 1
     *
     * Adds:
     * - Foreign key constraints on company_id
     * - Unique constraints combining company_id with business keys
     * - Indexes on company_id for query performance
     */
    public function up(): void
    {
        // BI Module Tables
        if (Schema::hasTable('bi_visualization_templates')) {
            Schema::table('bi_visualization_templates', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'name']);
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('bi_data_stories')) {
            Schema::table('bi_data_stories', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'title']);
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('bi_alert_rules')) {
            Schema::table('bi_alert_rules', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'name']);
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('bi_predictive_models')) {
            Schema::table('bi_predictive_models', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('bi_data_sources')) {
            Schema::table('bi_data_sources', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'name']);
                $table->index('company_id');
            });
        }

        // Messaging Module Tables
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('message_batches')) {
            Schema::table('message_batches', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('messaging_campaigns')) {
            Schema::table('messaging_campaigns', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'name']);
                $table->index('company_id');
            });
        }

        // MarketingAutomation Module Tables
        if (Schema::hasTable('push_notifications')) {
            Schema::table('push_notifications', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('sms_messages')) {
            Schema::table('sms_messages', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('dynamic_content_rules')) {
            Schema::table('dynamic_content_rules', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        // Ecommerce Module Tables
        if (Schema::hasTable('demand_forecasts')) {
            Schema::table('demand_forecasts', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'product_id', 'forecast_date']);
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('inventory_optimizations')) {
            Schema::table('inventory_optimizations', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'product_id']);
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('personalization_segments')) {
            Schema::table('personalization_segments', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'name']);
                $table->index('company_id');
            });
        }

        // Accounting Module Tables
        if (Schema::hasTable('revenue_contracts')) {
            Schema::table('revenue_contracts', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'contract_number']);
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('tax_compliance_reports')) {
            Schema::table('tax_compliance_reports', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('consolidation_records')) {
            Schema::table('consolidation_records', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        // CRM Module Tables
        if (Schema::hasTable('opportunities')) {
            Schema::table('opportunities', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('territories')) {
            Schema::table('territories', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'name']);
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('crm_campaigns')) {
            Schema::table('crm_campaigns', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->unique(['company_id', 'campaign_name']);
                $table->index('company_id');
            });
        }

        // Additional common tables for all modules
        // These should exist in most modules for proper multi-tenancy
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $tables = [
            'bi_visualization_templates',
            'bi_data_stories',
            'bi_alert_rules',
            'bi_predictive_models',
            'bi_data_sources',
            'messages',
            'message_batches',
            'messaging_campaigns',
            'push_notifications',
            'sms_messages',
            'dynamic_content_rules',
            'demand_forecasts',
            'inventory_optimizations',
            'personalization_segments',
            'revenue_contracts',
            'tax_compliance_reports',
            'consolidation_records',
            'opportunities',
            'territories',
            'crm_campaigns',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    // Drop foreign keys
                    $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = ? AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME IS NOT NULL", [$table]);
                    foreach ($foreignKeys as $fk) {
                        $table->dropForeign([$fk->CONSTRAINT_NAME]);
                    }

                    // Drop unique constraints
                    try {
                        $table->dropUnique($table . '_company_id_unique');
                    } catch (\Exception $e) {
                        // Constraint may not exist
                    }

                    // Drop indexes
                    try {
                        $table->dropIndex($table . '_company_id_index');
                    } catch (\Exception $e) {
                        // Index may not exist
                    }
                });
            }
        }
    }
};
