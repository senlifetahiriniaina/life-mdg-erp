<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('calendar_id')->constrained('calendar_calendars')->cascadeOnDelete();
            $table->string('title', 256);
            $table->text('description')->nullable();
            $table->timestamp('start_at')->index();
            $table->timestamp('end_at')->index();
            $table->boolean('all_day')->default(false);
            $table->string('location', 512)->nullable();
            $table->string('url', 512)->nullable();
            $table->string('recurrence_rule', 512)->nullable();
            $table->json('recurrence_exception_dates')->nullable();
            $table->string('status', 16)->default('confirmed')->index();
            $table->string('visibility', 16)->default('public');
            $table->string('source', 32)->default('internal');
            $table->string('external_event_id', 512)->nullable();
            $table->string('external_etag', 128)->nullable();
            $table->string('module_type', 64)->nullable()->index();
            $table->string('module_id', 36)->nullable();
            $table->string('color', 7)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
