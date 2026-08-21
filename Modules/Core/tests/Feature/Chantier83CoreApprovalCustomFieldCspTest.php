<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 8.3 (Core): CustomFieldController had CustomFieldPolicy fully
 * written but never called and never registered with Laravel's Gate — any
 * authenticated user could create/update/delete custom fields. Also covers
 * the CSP violation report/read endpoints, previously entirely unrouted.
 *
 * Chantier 32.1: the two "approval workflow" RBAC tests that used to live
 * here (ApprovalController/core/approvals/*) were removed along with the
 * confirmed-dead Core Approval engine they covered — see CLAUDE.md's
 * Chantier 32.1 entry.
 */
class Chantier83CoreApprovalCustomFieldCspTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_user_cannot_create_a_custom_field(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/core/custom-fields', [
            'entity_type' => 'crm_contacts',
            'field_key' => 'loyalty_tier',
            'field_label' => 'Loyalty Tier',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_custom_field(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/core/custom-fields', [
            'entity_type' => 'crm_contacts',
            'field_key' => 'loyalty_tier',
            'field_label' => 'Loyalty Tier',
        ]);

        $response->assertCreated();
    }

    public function test_csp_violation_report_is_public_and_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/core/csp/report', [
            'csp-report' => [
                'document-uri' => 'https://example.test/dashboard',
                'violated-directive' => "script-src 'self'",
                'blocked-uri' => 'https://evil.test/x.js',
            ],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseCount('csp_violations', 1);
    }

    public function test_plain_user_cannot_list_csp_violations(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/core/csp/violations');

        $response->assertForbidden();
    }

    public function test_admin_can_list_csp_violations(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/core/csp/violations');

        $response->assertOk();
    }
}
