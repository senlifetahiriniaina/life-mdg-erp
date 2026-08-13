<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (false) { // 'autopilot_configs' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('autopilot_configs', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index(); // null = global default
                $table->string('module', 64)->index();            // e.g. 'CRM', 'Inventory'
                $table->string('feature', 128)->index();          // e.g. 'auto_assign_leads'
                $table->boolean('enabled')->default(false);
                $table->json('config')->nullable();               // extra params (thresholds, etc.)
                $table->string('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'module', 'feature']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('autopilot_configs');
    }
};
