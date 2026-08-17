<?php

declare(strict_types=1);

namespace Modules\Security\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Chantier 6: Security/Index.vue existed but had no route anywhere
 * (RouteServiceProvider::map() only ever called mapApiRoutes()). Routed
 * at /security; its dashboard/summary fetch (no such endpoint) was
 * replaced with a client-side computation from the already-real
 * incidents/threat-indicators/auth-events/compliance-controls endpoints.
 */
class SecurityScreensWebTest extends TestCase
{
    public function test_index_renders_for_an_authenticated_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/security');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Security/Index', false));
    }

    public function test_index_is_unreachable_when_not_authenticated()
    {
        $response = $this->get('/security');

        $response->assertRedirect();
    }
}
