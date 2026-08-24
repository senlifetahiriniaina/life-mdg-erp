<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.18 (Payroll deep audit — layer 9, fake/dead) — SalaryComponent
 * (a per-tenant configurable earning/deduction component catalog) confirmed
 * dead via a repo-wide grep: zero controller, zero route, zero Vue page,
 * zero factory, and zero test anywhere referenced the model at all. Its one
 * reader, PayrollService::computeSalary()/getActiveComponents(), also had
 * zero callers of its own — a fully self-contained, never-wired parallel
 * salary-computation engine (base salary + configurable components -> gross/
 * net), superseded entirely by the real, tested, production engine
 * (PayrollIntegrationService::calculateSalaryComponents()/
 * calculateDeductions(), driven by real EmployeeCompensation +
 * StatutorySchemes data) — the same confirmed-dead-duplicate-parallel-
 * subsystem pattern already deleted repeatedly this session (CRM's
 * TerritoryManagementController, Achats' PurchaseApprovalChainService, HR's
 * AbsenceManagementService/AdvancedAttendanceService). Dropped rather than
 * built out, since wiring it to real payslip generation would mean
 * inventing new business logic (how a tenant-configurable component catalog
 * should interact with the already-real, already-correct compensation/tax
 * pipeline) that was never specified anywhere, not a wiring fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('salary_components');
    }

    public function down(): void
    {
        Schema::create('salary_components', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 100);
            $table->string('component_type', 30);
            $table->string('calculation_type', 30);
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('rate', 7, 4)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_statutory')->default(false);
            $table->json('applies_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
    }
};
