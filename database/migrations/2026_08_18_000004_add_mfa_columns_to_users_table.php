<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules\Core\Services\MFAService needs three more columns on `users` beyond
 * the `mfa_method` column added by 2026_05_29_000049_patch_wave37_security_tables:
 * the TOTP secret, a verified flag (separate from the method being *set*), and
 * the one-time-use backup codes list. Additive-only, Schema::hasColumn-guarded
 * so it's safe to run after that earlier migration in any environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'mfa_secret')) {
                $table->text('mfa_secret')->nullable()->after('mfa_method');
            }
            if (! Schema::hasColumn('users', 'mfa_verified')) {
                $table->boolean('mfa_verified')->default(false)->after('mfa_secret');
            }
            if (! Schema::hasColumn('users', 'mfa_backup_codes')) {
                $table->text('mfa_backup_codes')->nullable()->after('mfa_verified');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['mfa_secret', 'mfa_verified', 'mfa_backup_codes'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
