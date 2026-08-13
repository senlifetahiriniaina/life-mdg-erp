<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whb_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('whb_connections')->cascadeOnDelete();
            $table->string('resource_type', 64)->index();
            $table->string('permission_level', 32)->default('read');
            $table->boolean('is_granted')->default(false);
            $table->timestamps();

            $table->unique(['connection_id', 'resource_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whb_permissions');
    }
};
