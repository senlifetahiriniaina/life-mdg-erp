<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.2 (14-layer deep audit of Modules\AI), layer 9 (fake/dead) —
 * two real, migrated tables with a real backing model each, but confirmed
 * (grep across the whole repo, not just this module) to have zero readers
 * and zero writers anywhere:
 *
 * - `ai_anomalies` / `Modules\AI\Models\AiAnomaly`: the module's real,
 *   routed anomaly-detection feature (`AiAnomalyDetectionController` →
 *   `AiAnomalyDetectionService::getActiveAnomalies()`) computes anomalies
 *   on the fly from real business tables (inventory/accounting/HR) and
 *   stores them in the response cache only (`Cache::remember`) — it never
 *   touches this Eloquent model at all. This isn't a missing-wiring gap
 *   (there's no natural single producer to wire — the cache-backed design
 *   is deliberate and already the one, real, working implementation), it's
 *   a confirmed leftover from the original WideHalo extraction that was
 *   superseded before ever being used.
 * - `ai_recommendations` / `Modules\AI\Models\AiRecommendation`: no service
 *   anywhere generates a row here either — `Modules\Strategy\Services\
 *   StrategyAIService::recommend()` (the app's one real "AI
 *   recommendations" feature) is likewise entirely stateless, returning
 *   recommendations directly rather than persisting them.
 *
 * Matches this session's established "confirmed dead, drop the table"
 * precedent (see `2026_09_02_000001_drop_excluded_module_and_collision_
 * stub_tables.php` and siblings) rather than leaving inert scaffold debt.
 * This is a one-way cleanup — down() intentionally does not attempt to
 * recreate the dead schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_anomalies');
        Schema::dropIfExists('ai_recommendations');
    }

    public function down(): void
    {
        // Intentionally not recreated — see docblock above.
    }
};
