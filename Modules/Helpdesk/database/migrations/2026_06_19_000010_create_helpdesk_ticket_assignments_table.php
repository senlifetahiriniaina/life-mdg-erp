<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_ticket_assignments')) {
            return;
        }

        Schema::create('helpdesk_ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('assigned_to_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_by_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_ticket_assignments');
    }
};
