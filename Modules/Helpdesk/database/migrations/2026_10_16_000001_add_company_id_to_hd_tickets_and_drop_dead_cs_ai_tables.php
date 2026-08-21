<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.21 (Helpdesk 14-layer deep audit).
 *
 * Part 1 — the headline cross-tenant leak: `hd_tickets` never had a
 * company/tenant column at all. `TicketController::index()` had zero
 * filtering, and `employee` (this app's broad-by-design role) gets
 * `helpdesk.ticket.*` via the generic seeder loop, so any employee of any
 * company could list, view, assign, resolve, close, and escalate every
 * other company's tickets — confirmed empirically via a real HTTP request
 * as two separate companies. Additive nullable `company_id` (matching the
 * shape of every other tenant-scoping fix this session), populated going
 * forward from the acting user's `company_id` in `TicketService::
 * createFromSource()`.
 *
 * Part 2 — 4 confirmed fully-dead cs-ai models, dropped rather than kept
 * as speculative landmine insurance (the precedent this migration's own
 * sibling, `2026_08_23_000001_create_remaining_cs_ai_tables.php`, used to
 * justify building them in the first place). Unlike their 4 siblings
 * created in that same migration (EscalationHistory/ResponsePerformance/
 * SatisfactionHistory/SatisfactionModel — all real relation targets of
 * already-live CustomerServiceAIController-covered models), a full grep
 * confirmed `ConversationAnalytics`/`EscalationWorkflow`/
 * `ResponseCustomization`/`SatisfactionFactor` have zero relation from any
 * other model AND zero controller/route reference anywhere — genuinely
 * isolated, not just currently-unreachable. Building real producers for
 * them would mean inventing new scoring/workflow business rules never
 * specified anywhere (SatisfactionFactor's own field set doesn't even match
 * the deterministic factors SatisfactionPredictionService::
 * calculateSatisfactionFactors() already computes) — the same
 * confirmed-dead-delete precedent used repeatedly this session rather than
 * leaving a table nothing will ever populate.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('hd_tickets') && ! Schema::hasColumn('hd_tickets', 'company_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('id');
            });
        }

        Schema::dropIfExists('hd_conversation_analytics');
        Schema::dropIfExists('cs_escalation_workflows');
        Schema::dropIfExists('cs_response_customization');
        Schema::dropIfExists('cs_satisfaction_factors');
    }

    public function down(): void
    {
        if (Schema::hasTable('hd_tickets') && Schema::hasColumn('hd_tickets', 'company_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->dropColumn('company_id');
            });
        }

        // Dropped dead tables are not recreated on rollback — matching this
        // session's established precedent for confirmed-dead-code deletions
        // (e.g. Chantier 32.1's ApprovalWorkflow/WorkflowDefinition drops).
    }
};
