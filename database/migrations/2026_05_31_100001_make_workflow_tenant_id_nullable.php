<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Originally intended to make tenant_id nullable on workflows.
 * No longer needed since the WorkflowFactory now creates a proper tenant record
 * with a UUID id (matching stancl/tenancy schema) before creating workflows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op: the WorkflowFactory handles tenant creation for tests.
    }

    public function down(): void
    {
        // No-op
    }
};
