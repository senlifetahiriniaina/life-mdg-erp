<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Catalogue des coupures par devise
        Schema::create('pos_denominations', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3)->index();
            $table->decimal('value', 15, 4);
            $table->string('label', 50);
            $table->enum('type', ['banknote', 'coin'])->default('banknote');
            $table->json('country_codes')->nullable();  // ISO-2 codes
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['currency_code', 'value']);
        });

        // Comptage détaillé par coupure (ouverture/fermeture de caisse)
        Schema::create('pos_cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('denomination_id')->constrained('pos_denominations')->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->nullableMorphs('countable');  // shift ou session
            $table->enum('count_type', ['opening', 'closing', 'cash_in', 'cash_out', 'audit']);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['countable_type', 'countable_id', 'count_type']);
            $table->index('currency_code');
        });

        // Paramétrage multi-devises par caisse/config
        if (Schema::hasTable('pos_configs')) {
            Schema::table('pos_configs', function (Blueprint $table) {
                if (!Schema::hasColumn('pos_configs', 'accepted_currencies')) {
                    $table->json('accepted_currencies')->nullable()->after('currency');
                }
                if (!Schema::hasColumn('pos_configs', 'default_count_currency')) {
                    $table->string('default_count_currency', 3)->nullable()->after('accepted_currencies');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cash_counts');
        Schema::dropIfExists('pos_denominations');

        if (Schema::hasTable('pos_configs')) {
            Schema::table('pos_configs', function (Blueprint $table) {
                if (Schema::hasColumn('pos_configs', 'accepted_currencies')) {
                    $table->dropColumn('accepted_currencies');
                }
                if (Schema::hasColumn('pos_configs', 'default_count_currency')) {
                    $table->dropColumn('default_count_currency');
                }
            });
        }
    }
};
