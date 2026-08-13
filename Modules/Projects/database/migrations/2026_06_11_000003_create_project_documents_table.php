<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedBigInteger('attached_by')->nullable();
            $table->timestamp('attached_at')->useCurrent();

            $table->unique(['project_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
