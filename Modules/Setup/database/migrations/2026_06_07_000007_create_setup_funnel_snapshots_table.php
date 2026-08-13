<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('setup_funnel_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->string('period', 16);
            $table->date('snapshot_date');
            $table->unsignedInteger('started')->default(0);
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('abandoned')->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->decimal('avg_duration_minutes', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'period', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_funnel_snapshots');
    }
};
