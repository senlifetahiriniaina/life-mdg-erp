<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.8 — deep 14-layer audit of Modules\Shared (layers 5/9/10:
 * format de données / fake-dead / relationnel).
 *
 * Drops `shared_preferences` (tenant_id + user_id + preference_key/value,
 * unique per [tenant_id, user_id, preference_key]) — confirmed via a
 * repo-wide grep (not just this module) that it has NEVER had an Eloquent
 * model anywhere in the app, and is referenced nowhere outside its own
 * create-migration (no raw DB::table('shared_preferences') query either).
 * Unlike the catch-all-scaffold stub tables Chantier 9 already audited and
 * confirmed zero for this module ("Socle group... Shared... have zero —
 * every table comes from a dedicated real migration"), this one comes from
 * a real, purpose-built migration dated 2026-06-08 that was simply never
 * wired to any model/controller after — the exact "zero Eloquent model
 * anywhere" rule Chantier 9 already established for dropping a table,
 * applied here for the first time to this specific table.
 *
 * Investigated building a real Preference model + minimal get/set
 * controller instead of dropping (the module.json description does list
 * "preferences" among Shared's stated concerns, and the schema is a
 * textbook generic per-user key/value store) — rejected: zero current
 * caller anywhere in the app has ever needed generic key/value
 * preferences (every module that needs configurable per-tenant behavior
 * already has its own dedicated settings mechanism — e.g.
 * Modules\Settings\Models\Setting), so building CRUD around this table
 * would be inventing a new feature nobody asked for rather than wiring an
 * existing real need, the same "don't build speculative business logic"
 * discipline this session applies throughout. Matches the precedent set at
 * Chantier 8.5-light for Tag/Language (deleted alongside their equally
 * consumer-less tables) rather than the "gap d'adoption, keep" precedent
 * used for genuinely reusable *code* like MultiTenantScope/CustomFields —
 * this is a bare, code-less table, not an unused-but-real abstraction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shared_preferences');
    }

    public function down(): void
    {
        // Deliberately no-op: the dropped table backed zero Eloquent model
        // and zero reader/writer anywhere in the app — there is no code
        // left that expects this schema back.
    }
};
