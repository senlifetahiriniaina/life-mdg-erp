<?php

declare(strict_types=1);

namespace Modules\BI\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 8.2 (BI — AlertRule subsystem): AlertRuleController (16 methods —
 * threshold-based real-time alerts: rules, conditions, recipients,
 * escalations, DND schedules, history/acknowledge/resolve) and AlertPolicy
 * were fully written but unreachable — none of the 7 backing tables
 * (bi_alert_rules, bi_alert_conditions, bi_alert_recipients,
 * bi_alert_escalations, bi_alert_history, bi_dnd_schedules,
 * bi_alert_deduplication) existed, and the subsystem had no page or route.
 * See 2026_08_25_000001_create_bi_alert_rule_tables.php for the migration
 * and AlertRules/Index.vue for the new page. Distinct from the pre-existing
 * `Modules\BI\Models\Alert`/`AlertController` ("bi/alerts") subsystem.
 */
class Chantier82BiAlertRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_rules_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/alert-rules');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/AlertRules/Index', false));
    }

    public function test_admin_can_create_an_alert_rule(): void
    {
        $this->actingAsUser('admin');

        $response = $this->postJson('/api/v1/bi/alert-rules', [
            'name' => 'Stock critique - Riz local',
            'description' => 'Alerte quand le stock passe sous le seuil de réapprovisionnement',
            'metric_source' => 'inventory_stock_level',
            'metric_source_id' => 42,
            'is_public' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('bi_alert_rules', [
            'name' => 'Stock critique - Riz local',
            'metric_source' => 'inventory_stock_level',
            'status' => 'active',
        ]);
    }

    public function test_user_without_permission_cannot_create_an_alert_rule(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/bi/alert-rules', [
            'name' => 'Règle non autorisée',
            'metric_source' => 'inventory_stock_level',
            'metric_source_id' => 1,
        ]);

        $response->assertForbidden();
    }
}
