<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msg_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->enum('type', ['direct', 'group'])->default('direct');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('msg_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('msg_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('msg_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('msg_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msg_messages');
        Schema::dropIfExists('msg_conversation_participants');
        Schema::dropIfExists('msg_conversations');
    }
};
