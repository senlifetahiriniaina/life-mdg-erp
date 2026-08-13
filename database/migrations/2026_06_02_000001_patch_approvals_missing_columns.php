<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('approvals')) {
            Schema::table('approvals', function (Blueprint $table) {
                if (!Schema::hasColumn('approvals', 'assigned_to')) {
                    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('approvals', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approvals')) {
            Schema::table('approvals', function (Blueprint $table) {
                $table->dropForeignIdFor(\App\Models\User::class, 'assigned_to');
                $table->dropForeignIdFor(\App\Models\User::class, 'user_id');
            });
        }
    }
};
