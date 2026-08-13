<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        $this->patch('crm_leads', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_id'))         $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'email'))              $t->string('email', 150)->nullable();
            if (!Schema::hasColumn($table, 'phone'))              $t->string('phone', 50)->nullable();
            if (!Schema::hasColumn($table, 'company'))            $t->string('company', 200)->nullable();
            if (!Schema::hasColumn($table, 'opportunity_id'))     $t->unsignedBigInteger('opportunity_id')->nullable();
        });
    }

    public function down(): void {}
};
