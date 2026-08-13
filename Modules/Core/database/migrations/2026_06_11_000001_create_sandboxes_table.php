<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #19 — Tenant Sandbox Environments
 *
 * Creates the sandboxes table that links a cloned sandbox tenant back
 * to its parent tenant, tracks expiry and lifecycle status.
 *
 * Note: tenants.id is a string PK (non-incrementing), so FKs use string().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandboxes', function (Blueprint $table): void {
            $table->id();

            // The sandbox tenant (isolated DB, empty data)
            $table->string('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // The production tenant this sandbox was cloned from
            $table->string('parent_tenant_id');
            $table->foreign('parent_tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->string('name');

            $table->timestamp('expires_at')->nullable();

            $table->enum('status', ['active', 'expired', 'deleted'])->default('active');

            // Optional link to a company/org record inside the tenant's own DB
            $table->unsignedBigInteger('company_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common lookups
            $table->index('parent_tenant_id');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandboxes');
    }
};
