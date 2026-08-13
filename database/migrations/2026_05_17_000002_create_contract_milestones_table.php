<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_milestones')) {
            Schema::create('contract_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedTinyInteger('percentage'); // % of total contract
                $table->decimal('amount', 15, 2)->nullable();
                $table->dateTime('target_date');
                $table->dateTime('completed_date')->nullable();
                $table->dateTime('revenue_recognized_date')->nullable();
                $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
                $table->decimal('recognized_amount', 15, 2)->default(0);
                $table->text('performance_obligation_notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_milestones');
    }
};
