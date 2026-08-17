<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Tests\Feature;

use App\Models\User;
use Modules\Helpdesk\Models\ChatSession;
use Modules\Helpdesk\Models\ForumPost;
use Modules\Helpdesk\Models\KbCategory;
use Modules\Helpdesk\Models\KbPortalArticle;
use Modules\Helpdesk\Models\KbPortalCategory;
use Tests\TestCase;

/**
 * Chantier 6 Phase 4: Chat/Index.vue (real, routed) called 3 endpoints
 * (chat/queue, GET chat/sessions/{id}/messages, chat/sessions/{id}/close)
 * that were never routed at all — ChatController already implemented all
 * three, just unwired. Portal/Index.vue's feedback button targeted
 * kb/portal/articles/{id}/helpful, which didn't exist on KbPortalController
 * or the underlying table. Forum/Show.vue declared an `id` prop but the
 * web route sent `postId`, so every load requested `.../posts/undefined`.
 * AIBot/Index.vue and QualityAssurance/Index.vue were fully hardcoded mock
 * pages with no route at all.
 */
class HelpdeskScreensWebTest extends TestCase
{
    /**
     * ChatSessionFactory's own default definition uses fake()->word() for
     * assigned_agent_id/ticket_id/metadata/closed_at — garbage that crashes
     * the model's datetime/array casts. Every call below overrides those
     * fields explicitly rather than fixing the shared factory (out of scope
     * for this chantier).
     */
    private function chatSession(array $overrides = []): ChatSession
    {
        return ChatSession::factory()->create(array_merge([
            'started_at' => now(),
            'closed_at' => null,
            'assigned_agent_id' => null,
            'ticket_id' => null,
            'metadata' => [],
        ], $overrides));
    }

    public function test_chat_queue_endpoint_lists_waiting_and_active_sessions()
    {
        $agent = User::factory()->create();
        $this->chatSession(['status' => 'waiting']);
        $this->chatSession(['status' => 'active', 'assigned_agent_id' => $agent->id]);
        $this->chatSession(['status' => 'closed']);

        $response = $this->actingAs($agent, 'sanctum')->getJson('/api/v1/helpdesk/chat/queue');

        $response->assertOk();
        $this->assertCount(1, $response->json('waiting'));
        $this->assertCount(1, $response->json('active'));
    }

    public function test_chat_messages_endpoint_returns_session_messages()
    {
        $agent = User::factory()->create();
        $session = $this->chatSession(['status' => 'active']);

        $response = $this->actingAs($agent, 'sanctum')->getJson("/api/v1/helpdesk/chat/sessions/{$session->id}/messages");

        $response->assertOk();
        $response->assertJsonStructure(['messages', 'session_status', 'queue_position']);
    }

    public function test_chat_close_session_endpoint_closes_a_session()
    {
        $agent = User::factory()->create();
        $session = $this->chatSession(['status' => 'active']);

        $response = $this->actingAs($agent, 'sanctum')->postJson("/api/v1/helpdesk/chat/sessions/{$session->id}/close");

        $response->assertOk();
        $this->assertSame('closed', $session->fresh()->status);
    }

    public function test_kb_portal_article_helpful_endpoint_increments_counts()
    {
        $category = KbPortalCategory::factory()->create();
        $article = KbPortalArticle::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->postJson("/api/v1/helpdesk/kb/portal/articles/{$article->id}/helpful", ['helpful' => true]);

        $response->assertOk();
        $this->assertSame(1, $article->fresh()->helpful_count);
        $this->assertSame(0, $article->fresh()->not_helpful_count);
    }

    public function test_forum_show_renders_with_matching_id_prop()
    {
        $user = User::factory()->create();
        $post = ForumPost::factory()->create();

        $response = $this->actingAs($user)->get("/helpdesk/forum/{$post->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Helpdesk/Forum/Show', false)
            ->where('id', (string) $post->id)
        );
    }

    /**
     * GET helpdesk/kb/categories is registered twice — once against
     * KbCategoryController::index() (paginated) and again, later in the
     * same file, against KnowledgeBaseController::indexCategories() (a
     * plain ->get(), no pagination). Laravel's route table keys same
     * method+URI routes by their compiled signature, so the LAST
     * registration wins — KnowledgeBaseController, not KbCategoryController.
     * KnowledgeBase/Index.vue's `categories.value = data.data ?? data`
     * has to handle both shapes because of this; this test locks in which
     * one is actually live today.
     */
    public function test_knowledge_base_categories_endpoint_returns_flat_array()
    {
        $user = User::factory()->create();
        KbCategory::factory()->count(3)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/helpdesk/kb/categories');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_ai_bot_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/helpdesk/ai-bot');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Helpdesk/AIBot/Index', false));
    }

    public function test_quality_assurance_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/helpdesk/quality-assurance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Helpdesk/QualityAssurance/Index', false));
    }
}
