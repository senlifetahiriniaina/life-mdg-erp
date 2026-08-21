<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.2 (14-layer deep audit of Modules\AI) — closes a severe,
 * previously-undocumented gap found by empirically calling the module's own
 * real, routed endpoints rather than just reading the code.
 *
 * `Modules\AI\Services\AiUsageBudgetService::getUsage()`/`logUsage()`/
 * `getAdminSummary()` all read/write `ai_usage_logs` via raw `DB::table()`
 * calls — but no migration anywhere in the repo has ever created this table
 * (confirmed via `Schema::hasTable('ai_usage_logs')` returning false against
 * a freshly migrated database, and via a real HTTP request to the module's
 * own `GET /api/v1/ai/usage/me` endpoint, which 500s with
 * "SQLSTATE[HY000]: no such table: ai_usage_logs" for every user,
 * unconditionally, on every real call). This is a step worse than the
 * documented-elsewhere stub-table phenomenon: there wasn't even scaffolding.
 * The sibling `ai_usage_limits` table (same service, same feature area) WAS
 * migrated — see `database/migrations/2026_09_01_000001_...` — which is
 * exactly why this second, equally load-bearing table's absence went
 * undetected through 3 prior audit passes (Chantier 8.5-light, Chantier 10,
 * Chantier 19 Lot 3) that all touched this exact file for its tenant_id/role
 * phantom-column bugs without ever executing `myUsage()`/`adminUsage()`
 * against a real request.
 *
 * Columns match exactly what `AiUsageBudgetService::logUsage()` writes and
 * `getUsage()`/`getAdminSummary()` read — no speculative columns added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('module');
                $table->string('action');
                $table->string('locale', 8)->default('fr');
                $table->unsignedBigInteger('tokens_used')->default(0);
                $table->unsignedBigInteger('tokens_in')->default(0);
                $table->unsignedBigInteger('tokens_out')->default(0);
                $table->decimal('cost_usd', 12, 6)->default(0);
                $table->string('model')->default('claude-sonnet-4-6');
                $table->string('endpoint_type')->default('assist');
                $table->text('context_summary')->nullable();
                $table->boolean('from_cache')->default(false);
                $table->boolean('ai_enabled')->default(false);
                $table->unsignedInteger('response_time_ms')->default(0);
                $table->timestamps();

                $table->index('tenant_id');
                $table->index(['tenant_id', 'user_id']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
