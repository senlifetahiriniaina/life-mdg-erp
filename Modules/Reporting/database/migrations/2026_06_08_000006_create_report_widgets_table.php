<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_widgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('dashboard_id')->nullable();
            $table->string('name', 200);
            $table->string('widget_type', 50);
            $table->string('data_source', 100)->nullable();
            $table->json('query_config')->nullable();
            $table->json('display_config')->nullable();
            $table->integer('refresh_interval_seconds')->default(300);
            $table->timestamps();

            $table->index(['tenant_id', 'dashboard_id']);

            $table->foreign('dashboard_id')
                ->references('id')
                ->on('dashboards')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_widgets');
    }
};
