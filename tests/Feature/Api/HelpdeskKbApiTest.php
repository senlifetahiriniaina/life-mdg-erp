<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\KbArticle;
use Modules\Helpdesk\Models\KbCategory;

uses(RefreshDatabase::class);

function makeKbCategory(User $user, array $attrs = []): KbCategory
{
    return KbCategory::create(array_merge([
        'name'       => 'Test Category',
        'slug'       => 'test-category-' . uniqid(),
        'created_by' => $user->id,
        'is_active'  => true,
    ], $attrs));
}

function makeKbArticle(User $user, KbCategory $category, array $attrs = []): KbArticle
{
    return KbArticle::create(array_merge([
        'category_id' => $category->id,
        'created_by'  => $user->id,
        'title'       => 'Test Article',
        'slug'        => 'test-article-' . uniqid(),
        'content'     => 'This is a test article content.',
        'status'      => 'published',
    ], $attrs));
}

// ── Auth ──────────────────────────────────────────────────────────────────────

test('kb categories requires authentication', function () {
    $this->getJson('/api/v1/helpdesk/kb/categories')->assertUnauthorized();
});

test('kb articles requires authentication', function () {
    $this->getJson('/api/v1/helpdesk/kb/articles')->assertUnauthorized();
});

// ── Categories ────────────────────────────────────────────────────────────────

test('can list kb categories', function () {
     $user = actingAsUser('employee');
    makeKbCategory($user);
    makeKbCategory($user);
        $response = $this
        ->getJson('/api/v1/helpdesk/kb/categories')
        ->assertOk()
        ->assertJsonIsArray()
        ->assertJsonCount(2);
});

test('can create a kb category', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/helpdesk/kb/categories', [
            'name'       => 'Facturation',
            'sort_order' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Facturation');
});

test('category name is required', function () {
     $user = actingAsUser('employee');
                $response = $this
        ->postJson('/api/v1/helpdesk/kb/categories', [])
        ->assertUnprocessable();
});

test('can delete a kb category', function () {
     $user = actingAsUser('employee');
    $cat  = makeKbCategory($user);
        $response = $this
        ->deleteJson("/api/v1/helpdesk/kb/categories/{$cat->id}")
        ->assertNoContent();

    expect(KbCategory::find($cat->id))->toBeNull();
});

// ── Articles ──────────────────────────────────────────────────────────────────

test('can list kb articles', function () {
     $user = actingAsUser('employee');
    $cat  = makeKbCategory($user);
    makeKbArticle($user, $cat);
    makeKbArticle($user, $cat);
        $response = $this
        ->getJson('/api/v1/helpdesk/kb/articles')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('can create a kb article', function () {
     $user = actingAsUser('employee');
    $cat  = makeKbCategory($user);
        $response = $this
        ->postJson('/api/v1/helpdesk/kb/articles', [
            'category_id' => $cat->id,
            'title'       => 'Comment réinitialiser mon mot de passe ?',
            'content'     => '<p>Cliquez sur Mot de passe oublié…</p>',
            'status'      => 'published',
        ])
        ->assertCreated()
        ->assertJsonPath('title', 'Comment réinitialiser mon mot de passe ?')
        ->assertJsonPath('status', 'published');
});

test('article requires category_id', function () {
     $user = actingAsUser('employee');
                $response = $this
        ->postJson('/api/v1/helpdesk/kb/articles', ['title' => 'Test', 'content' => 'Content'])
        ->assertUnprocessable();
});

test('article show increments view count', function () {
     $user = actingAsUser('employee');
    $cat     = makeKbCategory($user);
    $article = makeKbArticle($user, $cat, ['view_count' => 0]);
        $response = $this
        ->getJson("/api/v1/helpdesk/kb/articles/{$article->id}")
        ->assertOk();

    expect($article->fresh()->view_count)->toBe(1);
});

test('can submit helpful feedback', function () {
     $user = actingAsUser('employee');
    $cat     = makeKbCategory($user);
    $article = makeKbArticle($user, $cat);
    $response = $this
        ->postJson("/api/v1/helpdesk/kb/articles/{$article->id}/feedback", ['helpful' => true])
        ->assertOk()
        ->json();

    expect($response['helpful_count'])->toBe(1);
    expect($response['not_helpful_count'])->toBe(0);
});

test('can submit not helpful feedback', function () {
     $user = actingAsUser('employee');
    $cat     = makeKbCategory($user);
    $article = makeKbArticle($user, $cat);
    $response = $this
        ->postJson("/api/v1/helpdesk/kb/articles/{$article->id}/feedback", ['helpful' => false])
        ->assertOk()
        ->json();

    expect($response['not_helpful_count'])->toBe(1);
});

test('feedback requires helpful boolean', function () {
     $user = actingAsUser('employee');
    $cat     = makeKbCategory($user);
    $article = makeKbArticle($user, $cat);
        $response = $this
        ->postJson("/api/v1/helpdesk/kb/articles/{$article->id}/feedback", [])
        ->assertUnprocessable();
});

test('can filter articles by category_id', function () {
     $user = actingAsUser('employee');
    $cat1 = makeKbCategory($user);
    $cat2 = makeKbCategory($user);
    makeKbArticle($user, $cat1);
    makeKbArticle($user, $cat1);
    makeKbArticle($user, $cat2);
        $response = $this
        ->getJson("/api/v1/helpdesk/kb/articles?category_id={$cat1->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('suggest requires q parameter', function () {
     $user = actingAsUser('employee');
                $response = $this
        ->getJson('/api/v1/helpdesk/kb/articles/suggest')
        ->assertUnprocessable();
});

test('suggest returns articles', function () {
     $user = actingAsUser('employee');
    $cat     = makeKbCategory($user);
    makeKbArticle($user, $cat, ['title' => 'Reset password guide', 'status' => 'published']);
        $response = $this
        ->getJson('/api/v1/helpdesk/kb/articles/suggest?q=password')
        ->assertOk()
        ->assertJsonIsArray();
});

test('can soft-delete an article', function () {
     $user = actingAsUser('employee');
    $cat     = makeKbCategory($user);
    $article = makeKbArticle($user, $cat);
        $response = $this
        ->deleteJson("/api/v1/helpdesk/kb/articles/{$article->id}")
        ->assertNoContent();

    expect(KbArticle::find($article->id))->toBeNull();
    expect(KbArticle::withTrashed()->find($article->id))->not->toBeNull();
});
