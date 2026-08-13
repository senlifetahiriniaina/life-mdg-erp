<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_companies')) {
            Schema::create('crm_companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('website')->nullable();
                $table->string('industry')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('crm_contacts')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_contacts', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('crm_contacts', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable();
                }
                if (! Schema::hasColumn('crm_contacts', 'merged_into_id')) {
                    $table->unsignedBigInteger('merged_into_id')->nullable();
                }
            });

            // Enforce email uniqueness (nullable emails are exempt in SQLite/MySQL).
            if (! $this->indexExists('crm_contacts', 'crm_contacts_email_unique')) {
                Schema::table('crm_contacts', function (Blueprint $table) {
                    $table->unique('email');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_contacts')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                foreach (['company_id', 'archived_at', 'merged_into_id'] as $col) {
                    if (Schema::hasColumn('crm_contacts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('crm_companies');
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return collect(\DB::select("PRAGMA index_list({$table})") ?? [])
                ->contains(fn ($i) => ($i->name ?? null) === $index);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
