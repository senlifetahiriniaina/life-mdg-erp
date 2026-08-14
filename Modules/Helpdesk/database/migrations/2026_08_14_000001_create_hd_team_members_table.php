<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Modules\Helpdesk\Models\Team::members()` has never existed, despite
 * being called by `TicketAssignmentService::assignRoundRobin()` and
 * exercised in `SlaAndAlertsTest.php` via `$team->members()->attach($user)`
 * — a BelongsToMany contract this table backs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_team_members')) {
            Schema::create('hd_team_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('hd_teams')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['team_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_team_members');
    }
};
