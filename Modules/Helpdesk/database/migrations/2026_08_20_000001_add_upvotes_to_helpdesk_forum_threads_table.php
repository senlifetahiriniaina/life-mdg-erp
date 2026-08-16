<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_forum_threads') && ! Schema::hasColumn('helpdesk_forum_threads', 'upvotes')) {
            Schema::table('helpdesk_forum_threads', function (Blueprint $table) {
                $table->integer('upvotes')->default(0)->after('is_answered');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('helpdesk_forum_threads') && Schema::hasColumn('helpdesk_forum_threads', 'upvotes')) {
            Schema::table('helpdesk_forum_threads', function (Blueprint $table) {
                $table->dropColumn('upvotes');
            });
        }
    }
};
