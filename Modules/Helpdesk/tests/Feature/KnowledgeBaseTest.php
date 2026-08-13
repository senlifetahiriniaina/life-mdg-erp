<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Helpdesk\Models\ArticleView;
use Modules\Helpdesk\Models\KbArticle;
use Modules\Helpdesk\Models\KbCategory;
use Modules\Helpdesk\Services\KnowledgeBaseService;


// ─── Model: KbCategory ────────────────────────────────────────────────────────

test('KbCategory isActive returns true when active', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['is_active' => true]);
    expect($category->isActive())->toBeTrue();
});

test('KbCategory isActive returns false when inactive', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['is_active' => false]);
    expect($category->isActive())->toBeFalse();
});

test('KbCategory isRoot returns true when no parent', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['parent_id' => null]);
    expect($category->isRoot())->toBeTrue();
});

test('KbCategory isRoot returns false when has parent', function () {
    actingAsUser('admin');
    $parent = KbCategory::factory()->create();
    $child = KbCategory::factory()->create(['parent_id' => $parent->id]);
    expect($child->isRoot())->toBeFalse();
});

test('KbCategory incrementArticleCount increments count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['article_count' => 0]);
    $category->incrementArticleCount();
    expect($category->fresh()->article_count)->toBe(1);
});

test('KbCategory decrementArticleCount decrements count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['article_count' => 3]);
    $category->decrementArticleCount();
    expect($category->fresh()->article_count)->toBe(2);
});

test('KbCategory decrementArticleCount does not go below zero', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['article_count' => 0]);
    $category->decrementArticleCount();
    expect($category->fresh()->article_count)->toBe(0);
});

test('KbCategory publishedArticleCount returns correct count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'created_by' => User::factory()->create()->id]);
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'created_by' => User::factory()->create()->id]);
    expect($category->publishedArticleCount())->toBe(1);
});

test('KbCategory has children relationship', function () {
    actingAsUser('admin');
    $parent = KbCategory::factory()->create();
    $child = KbCategory::factory()->create(['parent_id' => $parent->id]);
    expect($parent->children()->count())->toBe(1);
    expect($parent->children->first()->id)->toBe($child->id);
});

// ─── Model: KbArticle ─────────────────────────────────────────────────────────

test('KbArticle isPublished returns true when published', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'created_by' => User::factory()->create()->id]);
    expect($article->isPublished())->toBeTrue();
});

test('KbArticle isDraft returns true when draft', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'created_by' => User::factory()->create()->id]);
    expect($article->isDraft())->toBeTrue();
});

test('KbArticle publish sets status and published_at', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'created_by' => User::factory()->create()->id]);
    $article->publish();
    $article->refresh();
    expect($article->status)->toBe('published');
    expect($article->published_at)->not->toBeNull();
});

test('KbArticle publish increments category article count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['article_count' => 0]);
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'created_by' => User::factory()->create()->id]);
    $article->publish();
    expect($category->fresh()->article_count)->toBe(1);
});

test('KbArticle archive sets status to archived', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['article_count' => 1]);
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'created_by' => User::factory()->create()->id]);
    $article->archive();
    $article->refresh();
    expect($article->status)->toBe('archived');
});

test('KbArticle archive decrements category article count when was published', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create(['article_count' => 2]);
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'created_by' => User::factory()->create()->id]);
    $article->archive();
    expect($category->fresh()->article_count)->toBe(1);
});

test('KbArticle incrementView increments view_count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'view_count' => 5, 'created_by' => User::factory()->create()->id]);
    $article->incrementView();
    expect($article->fresh()->view_count)->toBe(6);
});

test('KbArticle markHelpful increments helpful_count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'helpful_count' => 2, 'created_by' => User::factory()->create()->id]);
    $article->markHelpful();
    expect($article->fresh()->helpful_count)->toBe(3);
});

test('KbArticle markNotHelpful increments not_helpful_count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'not_helpful_count' => 1, 'created_by' => User::factory()->create()->id]);
    $article->markNotHelpful();
    expect($article->fresh()->not_helpful_count)->toBe(2);
});

test('KbArticle helpfulnessRate calculates correctly', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create([
        'category_id' => $category->id,
        'helpful_count' => 3,
        'not_helpful_count' => 1,
        'created_by' => User::factory()->create()->id,
    ]);
    expect($article->helpfulnessRate())->toBe(75.0);
});

test('KbArticle helpfulnessRate returns 0 when no ratings', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create([
        'category_id' => $category->id,
        'helpful_count' => 0,
        'not_helpful_count' => 0,
        'created_by' => User::factory()->create()->id,
    ]);
    expect($article->helpfulnessRate())->toBe(0.0);
});

test('KbArticle computeReadingTime returns at least 1', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create([
        'category_id' => $category->id,
        'content' => 'Short.',
        'created_by' => User::factory()->create()->id,
    ]);
    expect($article->computeReadingTime())->toBeGreaterThanOrEqual(1);
});

test('KbArticle computeReadingTime is proportional to word count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    // 400 words → ~2 minutes
    $content = implode(' ', array_fill(0, 400, 'word'));
    $article = KbArticle::factory()->create([
        'category_id' => $category->id,
        'content' => $content,
        'created_by' => User::factory()->create()->id,
    ]);
    expect($article->computeReadingTime())->toBe(2);
});

// ─── Model: ArticleView ───────────────────────────────────────────────────────

test('ArticleView isHelpful returns true when helpful', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $view = ArticleView::factory()->create(['article_id' => $article->id, 'helpful' => true]);
    expect($view->isHelpful())->toBeTrue();
});

test('ArticleView isHelpful returns false when not helpful', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $view = ArticleView::factory()->create(['article_id' => $article->id, 'helpful' => false]);
    expect($view->isHelpful())->toBeFalse();
});

test('ArticleView hasRated returns false when helpful is null', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $view = ArticleView::factory()->create(['article_id' => $article->id, 'helpful' => null]);
    expect($view->hasRated())->toBeFalse();
});

test('ArticleView hasRated returns true when rated', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $view = ArticleView::factory()->create(['article_id' => $article->id, 'helpful' => true]);
    expect($view->hasRated())->toBeTrue();
});

// ─── Service: KnowledgeBaseService ───────────────────────────────────────────

test('KnowledgeBaseService createCategory creates with auto slug', function () {
    actingAsUser('admin');
    $user = User::factory()->create();
    $service = app(KnowledgeBaseService::class);
    $category = $service->createCategory(['name' => 'Getting Started', 'created_by' => $user->id]);
    expect($category->slug)->toBe('getting-started');
    expect($category->name)->toBe('Getting Started');
});

test('KnowledgeBaseService createArticle generates slug and reading time', function () {
    actingAsUser('admin');
    $user = User::factory()->create();
    $category = KbCategory::factory()->create();
    $service = app(KnowledgeBaseService::class);
    $article = $service->createArticle([
        'category_id' => $category->id,
        'title' => 'How to reset password',
        'content' => str_repeat('word ', 200),
        'created_by' => $user->id,
    ]);
    expect($article->slug)->toContain('how-to-reset-password');
    expect($article->reading_time_minutes)->toBeGreaterThanOrEqual(1);
});

test('KnowledgeBaseService publishArticle publishes article', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'created_by' => User::factory()->create()->id]);
    $service = app(KnowledgeBaseService::class);
    $result = $service->publishArticle($article);
    expect($result->status)->toBe('published');
    expect($result->published_at)->not->toBeNull();
});

test('KnowledgeBaseService search returns only published articles', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create(['category_id' => $category->id, 'title' => 'Password Reset Guide', 'status' => 'published', 'created_by' => $user->id]);
    KbArticle::factory()->create(['category_id' => $category->id, 'title' => 'Password draft', 'status' => 'draft', 'created_by' => $user->id]);
    $service = app(KnowledgeBaseService::class);
    $results = $service->search('Password');
    expect($results->every(fn ($a) => $a->status === 'published'))->toBeTrue();
});

test('KnowledgeBaseService recordView creates view record', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $service = app(KnowledgeBaseService::class);
    $view = $service->recordView($article, null, '127.0.0.1');
    expect($view)->toBeInstanceOf(ArticleView::class);
    expect($view->ip_address)->toBe('127.0.0.1');
    expect($article->fresh()->view_count)->toBe(1);
});

test('KnowledgeBaseService submitFeedback marks helpful', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'helpful_count' => 0, 'created_by' => User::factory()->create()->id]);
    $service = app(KnowledgeBaseService::class);
    $service->submitFeedback($article, true);
    expect($article->fresh()->helpful_count)->toBe(1);
});

test('KnowledgeBaseService submitFeedback marks not helpful', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'not_helpful_count' => 0, 'created_by' => User::factory()->create()->id]);
    $service = app(KnowledgeBaseService::class);
    $service->submitFeedback($article, false);
    expect($article->fresh()->not_helpful_count)->toBe(1);
});

test('KnowledgeBaseService getPopularArticles returns published by view count', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'view_count' => 10, 'created_by' => $user->id]);
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'view_count' => 50, 'created_by' => $user->id]);
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft',     'view_count' => 100, 'created_by' => $user->id]);
    $service = app(KnowledgeBaseService::class);
    $results = $service->getPopularArticles(10);
    expect($results->count())->toBe(2);
    expect($results->first()->view_count)->toBe(50);
});

test('KnowledgeBaseService getCategoryArticles returns published only by default', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'created_by' => $user->id]);
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'created_by' => $user->id]);
    $service = app(KnowledgeBaseService::class);
    $results = $service->getCategoryArticles($category);
    expect($results->count())->toBe(1);
    expect($results->first()->status)->toBe('published');
});

test('KnowledgeBaseService getStats returns correct structure', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'view_count' => 5, 'helpful_count' => 3, 'not_helpful_count' => 1, 'created_by' => $user->id]);
    $service = app(KnowledgeBaseService::class);
    $stats = $service->getStats();
    expect($stats)->toHaveKeys(['total_categories', 'total_articles', 'published_articles', 'total_views', 'avg_helpfulness_rate']);
    expect($stats['total_categories'])->toBeGreaterThanOrEqual(1);
    expect($stats['published_articles'])->toBeGreaterThanOrEqual(1);
    expect($stats['total_views'])->toBeGreaterThanOrEqual(5);
});

test('KnowledgeBaseService getSuggestionsForTicket returns matching published articles', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create(['category_id' => $category->id, 'title' => 'Cannot login issue', 'status' => 'published', 'created_by' => $user->id]);
    KbArticle::factory()->create(['category_id' => $category->id, 'title' => 'Billing questions', 'status' => 'published', 'created_by' => $user->id]);
    $service = app(KnowledgeBaseService::class);
    $results = $service->getSuggestionsForTicket('login');
    expect($results->count())->toBe(1);
    expect($results->first()->title)->toBe('Cannot login issue');
});

// ─── API: Categories ──────────────────────────────────────────────────────────

test('admin can list KB categories', function () {
    actingAsUser('admin');
    KbCategory::factory()->count(3)->create();
    $this->getJson('/api/v1/helpdesk/kb/categories')
        ->assertOk()
        ->assertJsonStructure([['id', 'name', 'slug']]);
});

test('admin can create a KB category', function () {
    actingAsUser('admin');
    $this->postJson('/api/v1/helpdesk/kb/categories', [
        'name' => 'Getting Started',
        'description' => 'Onboarding articles',
    ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Getting Started')
        ->assertJsonPath('slug', 'getting-started');
});

test('admin can show a KB category', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $this->getJson("/api/v1/helpdesk/kb/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('id', $category->id);
});

test('admin can update a KB category', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $this->putJson("/api/v1/helpdesk/kb/categories/{$category->id}", ['name' => 'Updated Name'])
        ->assertOk()
        ->assertJsonPath('name', 'Updated Name');
});

test('admin can delete a KB category', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $this->deleteJson("/api/v1/helpdesk/kb/categories/{$category->id}")
        ->assertStatus(204);
    expect(KbCategory::find($category->id))->toBeNull();
});

// ─── API: Articles ────────────────────────────────────────────────────────────

test('admin can list KB articles', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    KbArticle::factory()->count(2)->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $this->getJson('/api/v1/helpdesk/kb/articles')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('admin can create a KB article', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $this->postJson('/api/v1/helpdesk/kb/articles', [
        'category_id' => $category->id,
        'title' => 'How to reset your password',
        'content' => 'Go to login page and click forgot password.',
    ])
        ->assertStatus(201)
        ->assertJsonPath('title', 'How to reset your password');
});

test('admin can filter articles by category', function () {
    actingAsUser('admin');
    $cat1 = KbCategory::factory()->create();
    $cat2 = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create(['category_id' => $cat1->id, 'created_by' => $user->id]);
    KbArticle::factory()->create(['category_id' => $cat2->id, 'created_by' => $user->id]);
    $this->getJson("/api/v1/helpdesk/kb/articles?category_id={$cat1->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can filter articles by status', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'published', 'created_by' => $user->id]);
    KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'created_by' => $user->id]);
    $this->getJson('/api/v1/helpdesk/kb/articles?status=published')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can show a KB article and view count increments', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'view_count' => 0, 'created_by' => User::factory()->create()->id]);
    $this->getJson("/api/v1/helpdesk/kb/articles/{$article->id}")
        ->assertOk()
        ->assertJsonPath('id', $article->id);
    expect($article->fresh()->view_count)->toBe(1);
});

test('admin can update a KB article', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $this->putJson("/api/v1/helpdesk/kb/articles/{$article->id}", ['title' => 'Updated Title'])
        ->assertOk()
        ->assertJsonPath('title', 'Updated Title');
});

test('admin can delete a KB article', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $this->deleteJson("/api/v1/helpdesk/kb/articles/{$article->id}")
        ->assertStatus(204);
    expect(KbArticle::find($article->id))->toBeNull();
});

test('admin can publish a KB article via POST endpoint', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'created_by' => User::factory()->create()->id]);
    $this->postJson("/api/v1/helpdesk/kb/articles/{$article->id}/publish")
        ->assertOk()
        ->assertJsonPath('status', 'published');
});

test('admin can record a view via POST endpoint', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'created_by' => User::factory()->create()->id]);
    $this->postJson("/api/v1/helpdesk/kb/articles/{$article->id}/view")
        ->assertStatus(201);
    expect($article->fresh()->view_count)->toBe(1);
});

test('admin can submit helpful feedback', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'helpful_count' => 0, 'created_by' => User::factory()->create()->id]);
    $this->postJson("/api/v1/helpdesk/kb/articles/{$article->id}/feedback", ['helpful' => true])
        ->assertOk()
        ->assertJsonPath('helpful_count', 1);
});

test('admin can submit not helpful feedback', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['category_id' => $category->id, 'not_helpful_count' => 0, 'created_by' => User::factory()->create()->id]);
    $this->postJson("/api/v1/helpdesk/kb/articles/{$article->id}/feedback", ['helpful' => false])
        ->assertOk()
        ->assertJsonPath('not_helpful_count', 1);
});

test('admin can get popular articles', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->count(3)->create(['category_id' => $category->id, 'status' => 'published', 'created_by' => $user->id]);
    $this->getJson('/api/v1/helpdesk/kb/articles/popular')
        ->assertOk()
        ->assertJsonStructure([['id', 'title', 'view_count']]);
});

test('admin can search articles', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create([
        'category_id' => $category->id,
        'title' => 'Password Reset',
        'status' => 'published',
        'created_by' => $user->id,
    ]);
    $this->getJson('/api/v1/helpdesk/kb/articles/search?q=Password')
        ->assertOk();
});

test('search requires q parameter', function () {
    actingAsUser('admin');
    $this->getJson('/api/v1/helpdesk/kb/articles/search')
        ->assertStatus(422);
});

test('admin can get KB stats', function () {
    actingAsUser('admin');
    $this->getJson('/api/v1/helpdesk/kb/stats')
        ->assertOk()
        ->assertJsonStructure(['total_categories', 'total_articles', 'published_articles', 'total_views', 'avg_helpfulness_rate']);
});

test('admin can get article suggestions by subject', function () {
    actingAsUser('admin');
    $category = KbCategory::factory()->create();
    $user = User::factory()->create();
    KbArticle::factory()->create([
        'category_id' => $category->id,
        'title' => 'Cannot login to system',
        'status' => 'published',
        'created_by' => $user->id,
    ]);
    $this->getJson('/api/v1/helpdesk/kb/articles/suggestions?subject=login')
        ->assertOk()
        ->assertJsonCount(1);
});

test('suggestions requires subject parameter', function () {
    actingAsUser('admin');
    $this->getJson('/api/v1/helpdesk/kb/articles/suggestions')
        ->assertStatus(422);
});

// ─── API: Unauthenticated ─────────────────────────────────────────────────────

test('unauthenticated user cannot access KB categories', function () {
    auth()->logout();
    $this->getJson('/api/v1/helpdesk/kb/categories')
        ->assertUnauthorized();
});

test('unauthenticated user cannot create KB article', function () {
    auth()->logout();
    $this->postJson('/api/v1/helpdesk/kb/articles', [
        'title' => 'Test',
        'content' => 'Test content',
    ])
        ->assertUnauthorized();
});

test('unauthenticated user cannot view KB stats', function () {
    auth()->logout();
    $this->getJson('/api/v1/helpdesk/kb/stats')
        ->assertUnauthorized();
});
