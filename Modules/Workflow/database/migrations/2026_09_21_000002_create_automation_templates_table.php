<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19 Lot 3: `Modules\Workflow\Models\Automation\AutomationFlowTemplate`
 * (`$table = 'automation_templates'`) has never had a migration anywhere in
 * the repo at all — a step worse than the stub-table phenomenon documented
 * throughout CLAUDE.md, matching the precedent set by the Chantier 8.6/8.7
 * "5 real Eloquent models had $table declarations with no migration
 * anywhere" finding. Confirmed via `Schema::hasTable()` returning false and
 * a real `WorkflowTemplateController::index()` call throwing "no such
 * table" once its own separate fatal class-import bug (see that
 * controller's own docblock) was fixed. Closed here — matching this
 * session's "close the landmine before it's tripped" precedent — without
 * wiring routes/seeder into the live app, since the controller/route
 * surface for the whole AutomationFlow builder subsystem remains a
 * documented, separately-scoped gap (see routes/api.php's own note).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('icon')->nullable();
            $table->json('flow_definition');
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_templates');
    }
};
