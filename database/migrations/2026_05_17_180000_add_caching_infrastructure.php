<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create cache performance tracking table
        if (!Schema::hasTable('cache_performance_logs')) {
            Schema::create('cache_performance_logs', function (Blueprint $table) {
                $table->id();
                $table->string('cache_key');
                $table->enum('status', ['hit', 'miss', 'expired']);
                $table->integer('duration_ms')->default(0);
                $table->integer('response_size_bytes')->nullable();
                $table->string('route')->nullable();
                $table->string('user_id')->nullable()->index();
                $table->timestamp('accessed_at')->useCurrent()->index();
            });
        }

        // Create cache strategy configuration table
        if (!Schema::hasTable('cache_strategies')) {
            Schema::create('cache_strategies', function (Blueprint $table) {
                $table->id();
                $table->string('identifier')->unique();
                $table->string('description')->nullable();
                $table->enum('type', ['model', 'collection', 'aggregation', 'response'])->default('response');
                $table->json('config'); // TTL, tags, invalidation rules
                $table->boolean('is_enabled')->default(true);
                $table->integer('hit_count')->default(0);
                $table->integer('miss_count')->default(0);
                $table->timestamps();
            });
        }

        // Create cached query patterns table
        if (!Schema::hasTable('cached_query_patterns')) {
            Schema::create('cached_query_patterns', function (Blueprint $table) {
                $table->id();
                $table->string('pattern_name')->unique();
                $table->text('query_signature'); // Hash of query structure
                $table->json('parameters'); // Common filter parameters
                $table->integer('cache_ttl')->default(3600);
                $table->json('tags'); // Cache tags for invalidation
                $table->integer('execution_count')->default(0);
                $table->decimal('avg_duration_ms', 8, 2)->default(0);
                $table->boolean('is_optimized')->default(false);
                $table->timestamps();
                $table->index('pattern_name');
            });
        }

        // Add cache statistics indexes
        DB::statement('CREATE INDEX idx_cache_performance_route_time ON cache_performance_logs(route, accessed_at DESC)');
        DB::statement('CREATE INDEX idx_cache_performance_user_status ON cache_performance_logs(user_id, status, accessed_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cached_query_patterns');
        Schema::dropIfExists('cache_strategies');
        Schema::dropIfExists('cache_performance_logs');
    }
};
