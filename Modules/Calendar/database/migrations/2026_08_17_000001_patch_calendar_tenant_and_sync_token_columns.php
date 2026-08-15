<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('calendar_calendars') && Schema::hasColumn('calendar_calendars', 'tenant_id')) {
            // Calendar's own docblock declares `int|null $tenant_id` (and the root
            // database/migrations copy of this table made it nullable) — the module
            // migration that actually wins made it NOT NULL, so every insert without
            // an explicit tenant fails.
            Schema::table('calendar_calendars', function (Blueprint $table) {
                $table->string('tenant_id', 36)->nullable()->change();
            });
        }

        if (Schema::hasTable('calendar_sync_tokens')) {
            Schema::table('calendar_sync_tokens', function (Blueprint $table) {
                foreach ([
                    'tenant_id' => fn (Blueprint $t) => $t->string('tenant_id', 36)->nullable()->index(),
                    'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->index(),
                    'calendar_ids' => fn (Blueprint $t) => $t->json('calendar_ids')->nullable(),
                    'sync_errors' => fn (Blueprint $t) => $t->json('sync_errors')->nullable(),
                ] as $column => $adder) {
                    if (! Schema::hasColumn('calendar_sync_tokens', $column)) {
                        $adder($table);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('calendar_sync_tokens')) {
            Schema::table('calendar_sync_tokens', function (Blueprint $table) {
                $table->dropColumn(['tenant_id', 'user_id', 'calendar_ids', 'sync_errors']);
            });
        }
    }
};
