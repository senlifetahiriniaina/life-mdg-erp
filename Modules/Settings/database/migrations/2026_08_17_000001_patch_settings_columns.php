<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (Schema::hasColumn('settings', 'tenant_id')) {
            // Model docblock declares tenant_id nullable (global settings have
            // tenant_id = null, per Setting::scopeGlobal()/Setting::get()'s
            // null-tenant fallback), but the original migration made it NOT NULL.
            Schema::table('settings', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'module')) {
                // Not present at all — the model's $fillable/scopeForModule()/
                // Setting::get()/set() all key off `module`, which the original
                // migration never created (it only had `group_id`, a different concept).
                $table->string('module', 100)->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('settings', 'value_type')) {
                // Additive: original migration named this column `type`, the
                // model $fillable/casts use `value_type`.
                $table->string('value_type', 30)->default('string')->after('value');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['module', 'value_type']);
        });
    }
};
