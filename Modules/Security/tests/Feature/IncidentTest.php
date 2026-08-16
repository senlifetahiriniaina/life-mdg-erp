<?php

namespace Modules\Security\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\SecurityIncident;
use Modules\Security\Models\ThreatIndicator;
use Modules\Security\Models\IncidentResponse;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();
    }

    public function test_list_security_incidents(): void
    {
        SecurityIncident::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/incidents');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_create_security_incident(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/security/incidents', [
            'incident_type' => 'intrusion_attempt',
            'severity' => 'high',
            'description' => 'Multiple failed login attempts detected',
            'threat_indicators' => ['ip' => '192.168.1.100'],
            'affected_resources' => ['user_accounts'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('security_incidents', [
            'company_id' => $this->company->id,
            'incident_status' => 'open',
        ]);
    }

    public function test_view_security_incident(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/incidents/{$incident->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $incident->id);
    }

    public function test_update_security_incident(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->patchJson("/v1/security/incidents/{$incident->id}", [
            'description' => 'Updated description',
        ]);

        $response->assertOk();
        $response->assertJsonPath('description', 'Updated description');
    }

    public function test_investigate_incident(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create(['incident_status' => 'open']);

        $response = $this->actingAs($this->user)->postJson("/v1/security/incidents/{$incident->id}/investigate");

        $response->assertOk();
        $response->assertJsonPath('incident_status', 'investigating');
    }

    public function test_resolve_incident(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create(['incident_status' => 'investigating']);

        $response = $this->actingAs($this->user)->postJson("/v1/security/incidents/{$incident->id}/resolve", [
            'resolution_notes' => 'Blocked malicious IP, reset passwords',
        ]);

        $response->assertOk();
        $response->assertJsonPath('incident_status', 'resolved');
        $response->assertJsonPath('resolution_notes', 'Blocked malicious IP, reset passwords');
    }

    public function test_delete_resolved_incident(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create(['incident_status' => 'resolved']);

        $response = $this->actingAs($this->user)->deleteJson("/v1/security/incidents/{$incident->id}");

        $response->assertNoContent();
    }

    public function test_incident_types(): void
    {
        $types = ['intrusion_attempt', 'data_breach', 'policy_violation', 'anomaly'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->user)->postJson('/api/v1/security/incidents', [
                'incident_type' => $type,
                'severity' => 'medium',
                'description' => "Test $type",
            ]);

            $response->assertCreated();
        }
    }

    public function test_incident_severity_levels(): void
    {
        $severities = ['low', 'medium', 'high', 'critical'];

        foreach ($severities as $severity) {
            $response = $this->actingAs($this->user)->postJson('/api/v1/security/incidents', [
                'incident_type' => 'anomaly',
                'severity' => $severity,
                'description' => "Test $severity",
            ]);

            $response->assertCreated();
        }
    }

    public function test_list_threats(): void
    {
        ThreatIndicator::factory(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/threats');

        $response->assertOk();
    }

    public function test_create_threat_indicator(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/security/threats', [
            'indicator_type' => 'ip_address',
            'indicator_value' => '192.168.1.100',
            'threat_level' => 'high',
            'description' => 'Known malicious IP',
            'source' => 'internal_detection',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('security_threat_indicators', [
            'indicator_value' => '192.168.1.100',
        ]);
    }

    public function test_whitelist_threat(): void
    {
        $threat = ThreatIndicator::factory()->create(['is_whitelisted' => false]);

        $response = $this->actingAs($this->user)->postJson("/v1/security/threats/{$threat->id}/whitelist");

        $response->assertOk();
        $response->assertJsonPath('is_whitelisted', true);
    }

    public function test_unwhitelist_threat(): void
    {
        $threat = ThreatIndicator::factory()->create(['is_whitelisted' => true]);

        $response = $this->actingAs($this->user)->postJson("/v1/security/threats/{$threat->id}/unwhitelist");

        $response->assertOk();
        $response->assertJsonPath('is_whitelisted', false);
    }

    public function test_threat_indicator_types(): void
    {
        $types = ['ip_address', 'domain', 'hash', 'email', 'user_agent'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->user)->postJson('/api/v1/security/threats', [
                'indicator_type' => $type,
                'indicator_value' => "value_$type",
                'threat_level' => 'medium',
                'description' => "Test $type",
                'source' => 'user_report',
            ]);

            $response->assertCreated();
        }
    }

    public function test_threat_levels(): void
    {
        $levels = ['low', 'medium', 'high', 'critical'];

        foreach ($levels as $level) {
            $response = $this->actingAs($this->user)->postJson('/api/v1/security/threats', [
                'indicator_type' => 'ip_address',
                'indicator_value' => "ip_$level",
                'threat_level' => $level,
                'description' => "Test $level",
                'source' => 'threat_feed',
            ]);

            $response->assertCreated();
        }
    }

    public function test_add_incident_response(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->postJson("/v1/security/incidents/{$incident->id}/responses", [
            'response_type' => 'block',
            'response_config' => ['target' => '192.168.1.100'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('incident_responses', [
            'security_incident_id' => $incident->id,
        ]);
    }

    public function test_incident_company_isolation(): void
    {
        $otherCompany = Company::factory()->create();
        $incident = SecurityIncident::factory()->for($otherCompany)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/incidents/{$incident->id}");

        $response->assertForbidden();
    }

    public function test_incident_pagination(): void
    {
        SecurityIncident::factory(20)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/incidents?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 20);
    }

    public function test_filter_incidents_by_status(): void
    {
        SecurityIncident::factory(3)->for($this->company)->create(['incident_status' => 'open']);
        SecurityIncident::factory(2)->for($this->company)->create(['incident_status' => 'resolved']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/incidents?status=open');

        $response->assertOk();
    }

    public function test_incident_timestamps(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/incidents/{$incident->id}");

        $response->assertOk();
        $response->assertJsonPath('detected_at', fn($date) => $date !== null);
    }

    public function test_threat_indicator_uniqueness(): void
    {
        ThreatIndicator::factory()->create(['indicator_value' => '192.168.1.100']);

        $response = $this->actingAs($this->user)->postJson('/api/v1/security/threats', [
            'indicator_type' => 'ip_address',
            'indicator_value' => '192.168.1.100',
            'threat_level' => 'high',
            'description' => 'Duplicate',
            'source' => 'user_report',
        ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_resolve_non_investigating_incident(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create(['incident_status' => 'open']);

        $response = $this->actingAs($this->user)->postJson("/v1/security/incidents/{$incident->id}/resolve", [
            'resolution_notes' => 'Test',
        ]);

        $response->assertForbidden();
    }

    public function test_incident_affected_resources(): void
    {
        $resources = ['users', 'orders', 'payments'];

        $response = $this->actingAs($this->user)->postJson('/api/v1/security/incidents', [
            'incident_type' => 'data_breach',
            'severity' => 'critical',
            'description' => 'Test',
            'affected_resources' => $resources,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('affected_resources.0', 'users');
    }

    public function test_threat_expiration(): void
    {
        $threat = ThreatIndicator::factory()->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/security/threats');

        $response->assertOk();
    }

    public function test_incident_response_types(): void
    {
        $types = ['alert', 'block', 'quarantine', 'investigate', 'isolate'];
        $incident = SecurityIncident::factory()->for($this->company)->create();

        foreach ($types as $type) {
            $response = $this->actingAs($this->user)->postJson("/v1/security/incidents/{$incident->id}/responses", [
                'response_type' => $type,
            ]);

            $response->assertCreated();
        }
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $response = $this->getJson('/api/v1/security/incidents');

        $response->assertUnauthorized();
    }

    public function test_incident_soft_delete(): void
    {
        $incident = SecurityIncident::factory()->for($this->company)->create(['incident_status' => 'resolved']);

        $this->actingAs($this->user)->deleteJson("/v1/security/incidents/{$incident->id}");

        $this->assertNull(SecurityIncident::find($incident->id));
        $this->assertNotNull(SecurityIncident::withTrashed()->find($incident->id));
    }
}
