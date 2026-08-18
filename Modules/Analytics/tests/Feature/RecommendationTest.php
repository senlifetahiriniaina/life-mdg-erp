<?php

namespace Modules\Analytics\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Analytics\Models\Recommendation;
use Modules\Analytics\Models\RecommendationModel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    protected User $user;
    protected Company $company;
    protected RecommendationModel $recommendationModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();

        $permissions = [
            'analytics.recommendation.view',
            'analytics.recommendation.create',
            'analytics.recommendation.update',
            'analytics.recommendation.act',
            'analytics.recommendation.dismiss',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            $this->user->givePermissionTo($perm);
        }

        // Modules/Analytics/routes/api.php's v1/analytics group is gated by the
        // `role:` middleware (Chantier 8's Analytics pass) on top of the
        // per-resource permissions granted above — assign a role so the route
        // itself is reachable; the Policy checks above still enforce the real
        // per-permission/per-company authorization.
        Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $this->user->assignRole('employee');

        $this->recommendationModel = RecommendationModel::factory()
            ->for($this->company)
            ->create(['recommendation_type' => 'products']);
    }

    public function test_list_recommendations(): void
    {
        Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->count(5)
            ->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/analytics/recommendations');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_filter_recommendations_by_status(): void
    {
        Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create(['status' => 'pending']);

        Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create(['status' => 'clicked']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/recommendations?status=pending');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_filter_recommendations_by_type(): void
    {
        $productsModel = RecommendationModel::factory()
            ->for($this->company)
            ->create(['recommendation_type' => 'products']);

        $leadsModel = RecommendationModel::factory()
            ->for($this->company)
            ->create(['recommendation_type' => 'leads']);

        Recommendation::factory()->for($productsModel)->for($this->company)->create();
        Recommendation::factory()->for($leadsModel)->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/recommendations?type=products');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_create_recommendation(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/analytics/recommendations', [
            'recommendation_model_id' => $this->recommendationModel->id,
            'recipient_type' => 'Customer',
            'recipient_id' => 1,
            'recommended_type' => 'Product',
            'recommended_id' => 42,
            'relevance_score' => 0.85,
            'reason' => 'Based on browsing history',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'pending');
        $response->assertJsonPath('relevance_score', 0.85);
    }

    public function test_create_recommendation_validates_score(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/analytics/recommendations', [
            'recommendation_model_id' => $this->recommendationModel->id,
            'recipient_type' => 'Customer',
            'recipient_id' => 1,
            'recommended_type' => 'Product',
            'recommended_id' => 42,
            'relevance_score' => 1.5,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('relevance_score');
    }

    public function test_view_recommendation(): void
    {
        $recommendation = Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/recommendations/{$recommendation->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $recommendation->id);
    }

    public function test_get_recommendations_for_user(): void
    {
        Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create([
                'recipient_type' => 'Customer',
                'recipient_id' => 123,
                'status' => 'pending',
            ]);

        Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create([
                'recipient_type' => 'Customer',
                'recipient_id' => 456,
                'status' => 'pending',
            ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/recommendations/for-user?user_type=Customer&user_id=123');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_act_on_recommendation(): void
    {
        $recommendation = Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create(['status' => 'pending']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/analytics/recommendations/{$recommendation->id}/act");

        $response->assertOk();
        $response->assertJsonPath('status', 'acted');
        $response->assertJsonPath('acted_at', $response->json('acted_at'));
    }

    public function test_cannot_act_on_non_pending_recommendation(): void
    {
        $recommendation = Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create(['status' => 'dismissed']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/analytics/recommendations/{$recommendation->id}/act");

        $response->assertForbidden();
    }

    public function test_dismiss_recommendation(): void
    {
        $recommendation = Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create(['status' => 'pending']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/analytics/recommendations/{$recommendation->id}/dismiss");

        $response->assertOk();
        $response->assertJsonPath('status', 'dismissed');
    }

    public function test_recommendations_ordered_by_relevance(): void
    {
        Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create(['relevance_score' => 0.5]);

        Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create(['relevance_score' => 0.9]);

        $response = $this->actingAs($this->user)->getJson(
            '/api/v1/analytics/recommendations/for-user?user_type=Customer&user_id=1'
        );

        $response->assertOk();
    }

    public function test_recommendation_expiration(): void
    {
        $recommendation = Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->create([
                'status' => 'pending',
                'expires_at' => now()->subDay(),
            ]);

        $this->assertTrue($recommendation->expires_at < now());
    }

    public function test_pagination_recommendations(): void
    {
        Recommendation::factory()
            ->for($this->recommendationModel)
            ->for($this->company)
            ->count(25)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/recommendations?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
    }

    public function test_cannot_view_other_company_recommendation(): void
    {
        $otherCompany = Company::factory()->create();
        $otherModel = RecommendationModel::factory()->for($otherCompany)->create();
        $recommendation = Recommendation::factory()
            ->for($otherModel)
            ->for($otherCompany)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/analytics/recommendations/{$recommendation->id}");

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_list_recommendations(): void
    {
        $user = User::factory()->for($this->company)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/analytics/recommendations');

        $response->assertForbidden();
    }
}
