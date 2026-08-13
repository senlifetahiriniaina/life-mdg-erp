<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('report_id')->nullable();
            $table->unsignedBigInteger('dashboard_id')->nullable();
            $table->unsignedBigInteger('shared_with_user_id')->nullable();
            $table->string('shared_with_role', 100)->nullable();
            $table->string('permission', 20)->default('view');
            $table->string('share_token', 100)->unique()->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['tenant_id', 'report_id']);
            $table->index('share_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_shares');
    }
};
