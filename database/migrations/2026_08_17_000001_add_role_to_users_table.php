<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real RBAC is via spatie/laravel-permission (assignRole()/hasRole()), but
 * several legacy test files still do User::factory()->create(['role' => '...'])
 * against a column that never existed. Additive, nullable — no app code reads
 * or writes it.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 50)->nullable()->after('company_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
