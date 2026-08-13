<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shared_countries')) {
            Schema::create('shared_countries', function (Blueprint $table) {
                $table->id();
                $table->string('iso_alpha2', 2)->unique();
                $table->string('iso_alpha3', 3)->unique();
                $table->string('name', 100);
                $table->string('name_fr', 100)->nullable();
                $table->string('name_local', 100)->nullable();
                $table->string('currency_code', 3)->nullable()->index();
                $table->string('phone_prefix', 10)->nullable();
                $table->string('region', 50)->nullable()->index();
                $table->string('subregion', 100)->nullable();
                $table->boolean('is_ohada')->default(false)->index();
                $table->boolean('is_uemoa')->default(false);
                $table->boolean('is_cemac')->default(false);
                $table->decimal('vat_rate', 5, 2)->nullable()->comment('Default VAT rate %');
                $table->string('fiscal_year_start', 5)->nullable()->comment('MM-DD format');
                $table->string('timezone', 100)->nullable();
                $table->string('flag_emoji', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_countries');
    }
};
