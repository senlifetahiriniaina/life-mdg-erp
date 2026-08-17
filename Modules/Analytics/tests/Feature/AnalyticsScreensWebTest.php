<?php

declare(strict_types=1);

namespace Modules\Analytics\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Chantier 6: Analytics/Index.vue existed but had no route anywhere
 * (RouteServiceProvider::map() only ever called mapApiRoutes()) and called
 * 3 non-existent URLs (analytics/forecast-models, analytics/anomalies,
 * analytics/alerts). Routed at /analytics and rewired onto the real
 * forecasting/AI-anomaly endpoints.
 */
class AnalyticsScreensWebTest extends TestCase
{
    public function test_index_renders_for_an_authenticated_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/analytics');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Analytics/Index', false));
    }

    public function test_index_is_unreachable_when_not_authenticated()
    {
        $response = $this->get('/analytics');

        $response->assertRedirect();
    }
}
