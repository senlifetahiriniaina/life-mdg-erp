<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('connector_definitions')) {
            Schema::create('connector_definitions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index()->comment('NULL = global catalog connector');
                $table->string('key', 64)->index()->comment('e.g. slack, google_sheets');
                $table->string('name');
                $table->string('category', 64)->default('other')->comment('messaging|productivity|payments|crm|storage|email|accounting|dev');
                $table->string('auth_type', 16)->default('api_key')->comment('oauth2|api_key|basic');
                $table->string('base_url');
                $table->string('icon', 16)->default('🔌');
                $table->string('color', 16)->default('#64748B');
                $table->string('docs_url')->nullable();
                $table->json('auth_config')->nullable()->comment('declarative auth injection: in/name/prefix');
                $table->json('actions')->nullable()->comment('declarative action schemas');
                $table->json('triggers')->nullable()->comment('declarative trigger schemas');
                $table->unsignedInteger('rate_limit_per_minute')->default(60);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'key']);
            });
        }

        if (!Schema::hasTable('connector_credentials')) {
            Schema::create('connector_credentials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('connector_key', 64)->index();
                $table->string('name')->default('default');
                $table->text('credentials')->comment('encrypted JSON credential payload');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'connector_key', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_credentials');
        Schema::dropIfExists('connector_definitions');
    }
};
