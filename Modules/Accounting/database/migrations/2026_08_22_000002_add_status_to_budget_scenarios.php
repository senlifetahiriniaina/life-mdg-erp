<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acc_budget_scenarios', function (Blueprint $table): void {
            if (! Schema::hasColumn('acc_budget_scenarios', 'status')) {
                $table->string('status')->default('draft')->after('assumptions');
            }
            if (! Schema::hasColumn('acc_budget_scenarios', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('acc_budget_scenarios', function (Blueprint $table): void {
            if (Schema::hasColumn('acc_budget_scenarios', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('acc_budget_scenarios', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
