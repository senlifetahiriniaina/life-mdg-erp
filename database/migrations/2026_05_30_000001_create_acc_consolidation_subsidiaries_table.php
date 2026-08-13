<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acc_consolidation_subsidiaries')) {
            Schema::create('acc_consolidation_subsidiaries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consolidation_id');
                $table->unsignedBigInteger('company_id');
                $table->decimal('ownership_percentage', 5, 2)->default(100);
                $table->date('acquisition_date')->nullable();
                $table->timestamps();

                $table->foreign('consolidation_id')->references('id')->on('acc_consolidations')->onDelete('cascade');
                $table->foreign('company_id')->references('id')->on('acc_companies')->onDelete('cascade');
                $table->unique(['consolidation_id', 'company_id'], 'acc_consol_subs_consol_company_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_consolidation_subsidiaries');
    }
};
