<?php

namespace Modules\Security\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\ComplianceControl;
use Modules\Security\Models\ComplianceAudit;
use Modules\Security\Models\ComplianceViolation;
use Tests\TestCase;

class ComplianceTest extends TestCase
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

    public function test_list_compliance_controls(): void
    {
        ComplianceControl::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/v1/security/compliance/controls');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_create_compliance_control(): void
    {
        $response = $this->actingAs($this->user)->postJson('/v1/security/compliance/controls', [
            'framework' => 'GDPR',
            'control_id' => 'GDPR-001',
            'control_name' => 'Data Privacy',
            'control_description' => 'Ensure data privacy compliance',
            'control_type' => 'preventive',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('compliance_controls', [
            'framework' => 'GDPR',
            'control_id' => 'GDPR-001',
        ]);
    }

    public function test_view_compliance_control(): void
    {
        $control = ComplianceControl::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/compliance/controls/{$control->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $control->id);
    }

    public function test_update_compliance_control(): void
    {
        $control = ComplianceControl::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->patchJson("/v1/security/compliance/controls/{$control->id}", [
            'implementation_status' => 'implemented',
        ]);

        $response->assertOk();
        $response->assertJsonPath('implementation_status', 'implemented');
    }

    public function test_verify_compliance_control(): void
    {
        $control = ComplianceControl::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->postJson("/v1/security/compliance/controls/{$control->id}/verify");

        $response->assertOk();
        $response->assertJsonPath('implementation_status', 'verified');
        $response->assertJsonPath('last_verified_at', fn($date) => $date !== null);
    }

    public function test_delete_compliance_control(): void
    {
        $control = ComplianceControl::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->deleteJson("/v1/security/compliance/controls/{$control->id}");

        $response->assertNoContent();
    }

    public function test_compliance_frameworks(): void
    {
        $frameworks = ['SOX', 'HIPAA', 'PCI-DSS', 'GDPR'];

        foreach ($frameworks as $framework) {
            $response = $this->actingAs($this->user)->postJson('/v1/security/compliance/controls', [
                'framework' => $framework,
                'control_id' => "CTRL-$framework",
                'control_name' => "Control $framework",
                'control_description' => "Test $framework",
                'control_type' => 'preventive',
            ]);

            $response->assertCreated();
        }
    }

    public function test_control_types(): void
    {
        $types = ['preventive', 'detective', 'corrective'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->user)->postJson('/v1/security/compliance/controls', [
                'framework' => 'GDPR',
                'control_id' => "CTRL-$type",
                'control_name' => "Control $type",
                'control_description' => "Test $type",
                'control_type' => $type,
            ]);

            $response->assertCreated();
        }
    }

    public function test_list_compliance_audits(): void
    {
        ComplianceAudit::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/v1/security/compliance/audits');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_create_compliance_audit(): void
    {
        $response = $this->actingAs($this->user)->postJson('/v1/security/compliance/audits', [
            'audit_type' => 'scheduled',
            'framework' => 'GDPR',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('compliance_audits', [
            'company_id' => $this->company->id,
            'audit_status' => 'in_progress',
        ]);
    }

    public function test_view_compliance_audit(): void
    {
        $audit = ComplianceAudit::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/compliance/audits/{$audit->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $audit->id);
    }

    public function test_update_compliance_audit(): void
    {
        $audit = ComplianceAudit::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->patchJson("/v1/security/compliance/audits/{$audit->id}", [
            'controls_evaluated' => 50,
            'controls_compliant' => 45,
        ]);

        $response->assertOk();
        $response->assertJsonPath('controls_evaluated', 50);
    }

    public function test_complete_compliance_audit(): void
    {
        $audit = ComplianceAudit::factory()->for($this->company)->create(['audit_status' => 'in_progress']);

        $response = $this->actingAs($this->user)->postJson("/v1/security/compliance/audits/{$audit->id}/complete", [
            'compliance_score' => 95.5,
            'findings' => ['All controls verified'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('audit_status', 'completed');
        $response->assertJsonPath('compliance_score', 95.5);
    }

    public function test_delete_compliance_audit(): void
    {
        $audit = ComplianceAudit::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->deleteJson("/v1/security/compliance/audits/{$audit->id}");

        $response->assertNoContent();
    }

    public function test_audit_types(): void
    {
        $types = ['scheduled', 'on_demand', 'incident_response'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->user)->postJson('/v1/security/compliance/audits', [
                'audit_type' => $type,
                'framework' => 'GDPR',
            ]);

            $response->assertCreated();
        }
    }

    public function test_list_compliance_violations(): void
    {
        ComplianceViolation::factory(5)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/v1/security/compliance/violations');

        $response->assertOk();
    }

    public function test_view_compliance_violation(): void
    {
        $violation = ComplianceViolation::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/compliance/violations/{$violation->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $violation->id);
    }

    public function test_update_compliance_violation(): void
    {
        $violation = ComplianceViolation::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->patchJson("/v1/security/compliance/violations/{$violation->id}", [
            'violation_status' => 'remediated',
            'remediation_notes' => 'Issue resolved',
        ]);

        $response->assertOk();
        $response->assertJsonPath('violation_status', 'remediated');
    }

    public function test_violation_status_workflow(): void
    {
        $violation = ComplianceViolation::factory()->for($this->company)->create(['violation_status' => 'open']);

        $response = $this->actingAs($this->user)->patchJson("/v1/security/compliance/violations/{$violation->id}", [
            'violation_status' => 'remediated',
        ]);

        $response->assertOk();
        $response->assertJsonPath('violation_status', 'remediated');
    }

    public function test_violation_severity_levels(): void
    {
        $severities = ['low', 'medium', 'high'];

        foreach ($severities as $severity) {
            $violation = ComplianceViolation::factory()->for($this->company)->create(['severity' => $severity]);

            $response = $this->actingAs($this->user)->getJson("/v1/security/compliance/violations/{$violation->id}");

            $response->assertOk();
            $response->assertJsonPath('severity', $severity);
        }
    }

    public function test_control_company_isolation(): void
    {
        $otherCompany = Company::factory()->create();
        $control = ComplianceControl::factory()->for($otherCompany)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/compliance/controls/{$control->id}");

        $response->assertForbidden();
    }

    public function test_audit_pagination(): void
    {
        ComplianceAudit::factory(20)->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson('/v1/security/compliance/audits?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 20);
    }

    public function test_compliance_score_validation(): void
    {
        $audit = ComplianceAudit::factory()->for($this->company)->create(['audit_status' => 'in_progress']);

        $response = $this->actingAs($this->user)->postJson("/v1/security/compliance/audits/{$audit->id}/complete", [
            'compliance_score' => 150,
        ]);

        $response->assertUnprocessable();
    }

    public function test_audit_timestamps(): void
    {
        $audit = ComplianceAudit::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/compliance/audits/{$audit->id}");

        $response->assertOk();
        $response->assertJsonPath('audit_start_date', fn($date) => $date !== null);
    }

    public function test_violation_remediation_deadline(): void
    {
        $violation = ComplianceViolation::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)->getJson("/v1/security/compliance/violations/{$violation->id}");

        $response->assertOk();
    }

    public function test_control_implementation_details(): void
    {
        $details = ['step1' => 'Configure', 'step2' => 'Test'];

        $response = $this->actingAs($this->user)->postJson('/v1/security/compliance/controls', [
            'framework' => 'GDPR',
            'control_id' => 'GDPR-TEST',
            'control_name' => 'Test Control',
            'control_description' => 'Test',
            'control_type' => 'preventive',
            'implementation_details' => $details,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('implementation_details.step1', 'Configure');
    }

    public function test_audit_findings_tracking(): void
    {
        $findings = ['Finding 1', 'Finding 2'];
        $audit = ComplianceAudit::factory()->for($this->company)->create(['audit_status' => 'in_progress']);

        $response = $this->actingAs($this->user)->postJson("/v1/security/compliance/audits/{$audit->id}/complete", [
            'compliance_score' => 85,
            'findings' => $findings,
        ]);

        $response->assertOk();
        $response->assertJsonPath('findings.0', 'Finding 1');
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $response = $this->getJson('/v1/security/compliance/controls');

        $response->assertUnauthorized();
    }
}
