<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Tests\Feature;

use App\Models\User;
use Modules\Helpdesk\Models\Team;
use Tests\TestCase;

/**
 * Team::members() never existed, despite being called by
 * TicketAssignmentService::assignRoundRobin() and Team::withCount('members')
 * in TeamController::index() — meaning GET /api/v1/helpdesk/teams was fatal
 * on every request, not just the round-robin auto-assignment feature.
 */
class TeamMembersTest extends TestCase
{
    public function test_index_no_longer_fatals_on_member_count()
    {
        $user = User::factory()->create();
        Team::factory()->count(2)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/helpdesk/teams');

        $response->assertOk();
    }

    public function test_show_eager_loads_members()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $agent = User::factory()->create();
        $team->members()->attach($agent);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/helpdesk/teams/{$team->id}");

        $response->assertOk();
        $response->assertJsonPath('members.0.id', $agent->id);
    }

    public function test_members_relation_supports_attach_and_detach()
    {
        $team = Team::factory()->create();
        $agent = User::factory()->create();

        $team->members()->attach($agent);
        $this->assertTrue($team->members()->where('users.id', $agent->id)->exists());

        $team->members()->detach($agent);
        $this->assertFalse($team->members()->where('users.id', $agent->id)->exists());
    }
}
