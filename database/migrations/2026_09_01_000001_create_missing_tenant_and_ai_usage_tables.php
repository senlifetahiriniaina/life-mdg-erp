<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.6 consolidation: 5 real Eloquent models declared a $table with
 * no migration anywhere in the repo — worse than the stub-table phenomenon
 * documented elsewhere in this codebase, since there wasn't even scaffolding.
 * Surfaced by the Phase 8.7 documentation audit (docs/06-BASE-DE-DONNEES/).
 *
 * tenant_audit_log/tenant_users are actively written by
 * Modules\Core\Services\TenantManagerService (provision/suspend/reactivate/
 * upgrade-plan/purge/export — all reachable via SuperadminController, routed
 * in Chantier 8.3cs) — every one of those calls was hitting "table not
 * found". ai_usage_limits is actively read/written by
 * Modules\AI\Services\AiUsageBudgetService and
 * AiActionAdvisorController::limits()/setLimit() (routed, admin-only).
 * achats_purchase_invoice_matches backs Modules\Achats\Services\
 * ThreeWayMatchService, currently reachable only via the still-unrouted
 * ThreeWayMatchController — not yet a live landmine, but closed here at the
 * same time since the fix is free once the pattern is established.
 * tenant_invitations has no live caller yet either (TenantManagerService
 * never sends invitations) but is the natural sibling of tenant_users for
 * the same onboarding flow, so it's included rather than left half-built.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_users')) {
            Schema::create('tenant_users', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role')->default('user');
                $table->timestamp('joined_at')->nullable();
                $table->unsignedBigInteger('invited_by')->nullable();

                $table->index('tenant_id');
                $table->index('user_id');
                $table->unique(['tenant_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('tenant_invitations')) {
            Schema::create('tenant_invitations', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('email');
                $table->string('role')->default('user');
                $table->string('token', 64)->unique();
                $table->unsignedBigInteger('invited_by')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('email');
            });
        }

        if (! Schema::hasTable('tenant_audit_log')) {
            Schema::create('tenant_audit_log', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action');
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('tenant_id');
                $table->index('action');
            });
        }

        if (! Schema::hasTable('ai_usage_limits')) {
            Schema::create('ai_usage_limits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('limit_type');
                $table->decimal('limit_value', 18, 4);
                $table->string('period')->default('monthly');
                $table->boolean('block_on_exceed')->default(true);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index('tenant_id');
                $table->index(['tenant_id', 'user_id', 'limit_type']);
            });
        }

        if (! Schema::hasTable('achats_purchase_invoice_matches')) {
            Schema::create('achats_purchase_invoice_matches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('purchase_receipt_id');
                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->decimal('quantity_variance', 18, 4)->default(0);
                $table->decimal('price_variance', 18, 4)->default(0);
                $table->string('match_result')->default('pending');
                $table->json('mismatch_details')->nullable();
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('purchase_receipt_id');
                $table->index('purchase_order_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('achats_purchase_invoice_matches');
        Schema::dropIfExists('ai_usage_limits');
        Schema::dropIfExists('tenant_audit_log');
        Schema::dropIfExists('tenant_invitations');
        Schema::dropIfExists('tenant_users');
    }
};
