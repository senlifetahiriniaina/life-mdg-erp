<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('validation_rules')) {
            Schema::create('validation_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('field');
                $table->string('type', 30);
                $table->json('params')->nullable();
                $table->string('message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('validation_rule_sets')) {
            Schema::create('validation_rule_sets', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('validation_rule_set_rule')) {
            Schema::create('validation_rule_set_rule', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('rule_set_id');
                $table->unsignedBigInteger('rule_id');
                $table->timestamps();

                $table->unique(['rule_set_id', 'rule_id']);
            });
        }

        if (! Schema::hasTable('validation_rule_dependencies')) {
            Schema::create('validation_rule_dependencies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('rule_id');
                $table->unsignedBigInteger('depends_on_rule_id');
                $table->timestamps();

                $table->unique(['rule_id', 'depends_on_rule_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_rule_dependencies');
        Schema::dropIfExists('validation_rule_set_rule');
        Schema::dropIfExists('validation_rule_sets');
        Schema::dropIfExists('validation_rules');
    }
};
