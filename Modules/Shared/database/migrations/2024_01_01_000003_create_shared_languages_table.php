<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shared_languages')) {
            Schema::create('shared_languages', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name', 100);
                $table->string('native_name', 100)->nullable();
                $table->boolean('rtl')->default(false);
                $table->boolean('active')->default(true)->index();
                $table->string('region', 100)->nullable();
                $table->string('flag_emoji', 10)->nullable();
                $table->string('locale_code', 10)->nullable()->comment('Full locale e.g. fr_FR');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_languages');
    }
};
