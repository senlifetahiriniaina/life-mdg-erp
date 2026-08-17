<?php

declare(strict_types=1);

namespace Modules\BI\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 8.2 (BI — DataStory subsystem): DataStoryController (15 methods —
 * data storytelling: create/edit/publish/share slides, narrative flows, view
 * analytics) and DataStoryPolicy were fully written but unreachable — none of
 * the 5 backing tables (bi_data_stories, bi_story_slides, bi_narrative_flows,
 * bi_story_analytics, bi_story_views) existed, and the module had no page or
 * route pointing at it. See 2026_08_25_000002_create_bi_data_story_tables.php
 * for the migration and DataStories/Index.vue for the new page.
 */
class Chantier82BiDataStoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_stories_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/data-stories');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/DataStories/Index', false));
    }

    public function test_admin_can_create_a_data_story(): void
    {
        $this->actingAsUser('admin');

        $response = $this->postJson('/api/v1/bi/data-stories', [
            'title' => 'Ventes T3 2026',
            'description' => 'Analyse narrative des ventes du troisième trimestre',
            'summary' => 'Croissance de 12% par rapport au T2',
            'is_public' => false,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('bi_data_stories', [
            'title' => 'Ventes T3 2026',
            'status' => 'draft',
        ]);
    }

    public function test_user_without_permission_cannot_create_a_data_story(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/bi/data-stories', [
            'title' => 'Story non autorisée',
        ]);

        $response->assertForbidden();
    }
}
