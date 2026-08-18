<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the backing tables for the two items removed from CLAUDE.md's "10 items
 * deliberately left unbuilt" list — Mobile device auth and ASC606 Revenue Recognition —
 * now that their code (models, controllers, services, jobs, policies, factories, tests,
 * Vue pages) has been deleted outright rather than carried as documented dead weight.
 *
 * Mobile device auth (Modules/Core):
 *   `mobile_sessions` / `mobile_login_logs` were referenced only by the deleted
 *   MobileAuthServiceTest.php's own assertions — neither ever had a real migration
 *   anywhere in the repo (confirmed via grep across database/migrations/ and Modules/),
 *   so MobileAuthService's stub methods never actually touched the database. Dropped
 *   here only as a defensive no-op guard in case either table exists in an environment
 *   that ran an out-of-tree migration.
 *
 * ASC606 Revenue Recognition (Modules/Accounting):
 *   The catch-all scaffold migration (2026_05_29_000003) created 3 bare
 *   id/tenant_id/data/timestamps stub tables the RevenueContract model was actually
 *   bound to (`acc_revenue_contracts`, `acc_revenue_recognition_events`,
 *   `acc_revenue_recognition_policies`) — matching CLAUDE.md's original note.
 *
 *   Investigating this item turned up a second, larger cluster CLAUDE.md's note didn't
 *   mention: `2026_05_29_000001_create_accounting_core_tables.php` separately created
 *   real, fully-fleshed-out tables (`revenue_contracts`, `revenue_recognition_schedules`,
 *   `contract_liabilities`) with genuine ASC606 columns, backing a real
 *   ASC606RevenueRecognitionService (4 recognition methods), RevenueContractController,
 *   RevenueContractPolicy, and 2 async jobs. None of it was ever wired up though: the
 *   RevenueContract model was bound to the wrong (`acc_`-stub) table instead of the real
 *   one, imported a `RevenueRecognitionEvent` class that doesn't exist anywhere in the
 *   repo, was missing the `customer()`/`recognitionSchedules()`/`liability()` relations
 *   its own controller/policy called, and had zero routes registered anywhere — so despite
 *   the larger footprint this was still dead, unreachable scaffolding, not a later
 *   chantier's real build-out. See CLAUDE.md's "Known gaps" section for the full account.
 *
 *   All 6 of these tables (3 stub + 3 real-but-orphaned) are dropped here; children
 *   before the parent since `revenue_recognition_schedules`/`contract_liabilities` carry
 *   a (non-FK-constrained) `revenue_contract_id` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mobile device auth (Modules/Core) — defensive guard only, neither table was
        // ever actually migrated in this repo.
        foreach (['mobile_sessions', 'mobile_login_logs'] as $table) {
            Schema::dropIfExists($table);
        }

        // ASC606 Revenue Recognition (Modules/Accounting) — children before parent.
        foreach ([
            'revenue_recognition_schedules',
            'contract_liabilities',
            'revenue_contracts',
            'acc_revenue_recognition_events',
            'acc_revenue_recognition_policies',
            'acc_revenue_contracts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Intentionally left empty — these are removed-feature tables (dead stub schema
        // plus a real-but-never-wired schema with no live reader anywhere in the repo),
        // not worth restoring. Matches the down() convention already established by
        // 2026_05_29_000003_create_all_missing_module_tables.php and this session's other
        // destructive-cleanup migrations.
    }
};
