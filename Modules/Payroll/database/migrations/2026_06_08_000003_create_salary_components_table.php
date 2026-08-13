<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 100);
            $table->string('component_type', 30);
            $table->string('calculation_type', 30);
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('rate', 7, 4)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_statutory')->default(false);
            $table->json('applies_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
