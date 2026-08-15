<?php

namespace Modules\CRM\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Campaign;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_create_campaign(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/v1/crm/campaigns', [
            'name'        => 'Q2 2026 Outreach',
            'description' => 'Campaign for Q2 2026',
            'type'        => 'email',
            'channels'    => ['email'],
            'segments'    => ['high_value', 'enterprise'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_campaigns', [
            'name'   => 'Q2 2026 Outreach',
            'status' => 'draft',
        ]);
    }

    public function test_user_can_list_campaigns(): void
    {
        $this->actingAs($this->user, 'sanctum');

        Campaign::factory()->count(3)->create(['owner_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/crm/campaigns');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_launch_campaign(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $campaign = Campaign::factory()->create(['owner_id' => $this->user->id, 'status' => 'draft']);

        $response = $this->postJson("/api/v1/crm/campaigns/{$campaign->id}/launch");

        $response->assertStatus(200);
        $this->assertTrue($campaign->fresh()->status === 'active');
    }

    public function test_campaign_tracks_analytics(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $campaign = Campaign::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/crm/campaigns/{$campaign->id}/analytics");

        $response->assertStatus(200);
        $response->assertJsonStructure(['summary', 'daily_analytics']);
    }
}
