<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash/bank import operation templates — the catalogue TreasuryImportService
 * matches imported caisse/relevé-bancaire rows against to propose the right
 * double-entry pairing (treasury account debited/credited against a fixed
 * counterpart GL account) before the user validates. No such concept existed
 * anywhere in this app before (confirmed via a full grep for
 * template/rule/categoriz across Modules/Accounting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_operation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->enum('nature', ['encaissement', 'decaissement']);
            $table->string('counterpart_account_code');
            $table->json('keywords')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();

            $table->index(['nature', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_operation_templates');
    }
};
