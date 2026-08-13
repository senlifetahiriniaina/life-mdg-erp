<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('cookie_consent')->default(false)->after('locale');
            $table->boolean('marketing_consent')->default(false)->after('cookie_consent');
            $table->timestamp('cookie_consent_at')->nullable()->after('marketing_consent');
            $table->timestamp('marketing_consent_at')->nullable()->after('cookie_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cookie_consent', 'marketing_consent', 'cookie_consent_at', 'marketing_consent_at']);
        });
    }
};
