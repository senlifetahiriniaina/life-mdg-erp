<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * setup_onboarding_step_events has two NOT NULL columns that block every
 * real write from OnboardingMetricsService::recordStep():
 *
 * - event_type is a legacy column superseded by `event` (added in
 *   2026_08_17_000001_patch_field_mappings_source_schemas_columns.php,
 *   which relaxed the sibling legacy column step_name to nullable but
 *   missed event_type). No real code path populates it anymore.
 * - duration_seconds is declared nullable in the model's own docblock
 *   (int|null) and recordStep() intentionally passes null when duration
 *   is 0 (`$durationSeconds > 0 ? $durationSeconds : null`), but the
 *   original migration only gave it a default(0) with no ->nullable(),
 *   which does not allow an explicit null on insert.
 * - occurred_at is another legacy column from the original migration
 *   (NOT NULL, no default) that the model never populates at all -- the
 *   model uses created_at/updated_at instead ($timestamps = false only
 *   disables Eloquent's automatic timestamp maintenance, it does not
 *   remove the columns, which are both already nullable in the schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('setup_onboarding_step_events')) {
            if (Schema::hasColumn('setup_onboarding_step_events', 'event_type')) {
                Schema::table('setup_onboarding_step_events', function (Blueprint $table) {
                    $table->string('event_type', 32)->nullable()->change();
                });
            }
            if (Schema::hasColumn('setup_onboarding_step_events', 'duration_seconds')) {
                Schema::table('setup_onboarding_step_events', function (Blueprint $table) {
                    $table->unsignedInteger('duration_seconds')->nullable()->default(null)->change();
                });
            }
            if (Schema::hasColumn('setup_onboarding_step_events', 'occurred_at')) {
                Schema::table('setup_onboarding_step_events', function (Blueprint $table) {
                    $table->timestamp('occurred_at')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Not reversible without a value to backfill -- these columns stay nullable.
    }
};
