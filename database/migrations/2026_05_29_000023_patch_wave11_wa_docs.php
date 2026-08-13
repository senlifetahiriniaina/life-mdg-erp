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
        // ── whatsapp_payments ─────────────────────────────────────────────
        $this->patch('whatsapp_payments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'conversation_id'))  $t->unsignedBigInteger('conversation_id')->nullable();
            if (!Schema::hasColumn($table, 'amount'))           $t->decimal('amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'currency'))         $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'gateway'))          $t->string('gateway', 30)->nullable();
            if (!Schema::hasColumn($table, 'reference'))        $t->string('reference')->nullable();
        });

        // ── whatsapp_optins ───────────────────────────────────────────────
        $this->patch('whatsapp_optins', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'phone'))         $t->string('phone')->nullable();
            if (!Schema::hasColumn($table, 'opted_in'))      $t->boolean('opted_in')->default(true);
            if (!Schema::hasColumn($table, 'opted_in_at'))   $t->timestamp('opted_in_at')->nullable();
            if (!Schema::hasColumn($table, 'opted_out_at'))  $t->timestamp('opted_out_at')->nullable();
        });

        // ── whatsapp_broadcasts ───────────────────────────────────────────
        $this->patch('whatsapp_broadcasts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))       $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'message'))      $t->text('message')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))      $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'sent_count'))   $t->integer('sent_count')->default(0);
        });

        // ── doc_approval_workflows ────────────────────────────────────────
        $this->patch('doc_approval_workflows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'steps'))        $t->text('steps')->nullable();
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'document_id'))  $t->unsignedBigInteger('document_id')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void {}
};
