<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\ImportJob;
use Tests\TestCase;

/**
 * Chantier 32.1 — deep 14-layer audit of Modules/Core. Locks in every real
 * bug found and fixed, via real HTTP routes / real job dispatch, never a
 * bare object construction.
 */
class Chantier32CoreDeepAuditTest extends TestCase
{
    use RefreshDatabase;

    // ─── Layer 9 (Fake/Dead) — confirmed-dead generic engines removed ─────────

    public function test_the_dead_core_workflow_engine_routes_no_longer_exist(): void
    {
        $this->actingAsUser('employee');

        $this->getJson('/api/v1/core/workflows')->assertNotFound();
        $this->getJson('/api/v1/core/workflows/accounting/Invoice')->assertNotFound();
    }

    public function test_the_dead_core_approval_engine_routes_no_longer_exist(): void
    {
        $this->actingAsUser('admin');

        $this->getJson('/api/v1/core/approvals/workflows')->assertNotFound();
        $this->postJson('/api/v1/core/approvals/workflows', [
            'name' => 'x', 'module' => 'accounting', 'resource_type' => 'invoice', 'steps' => [],
        ])->assertNotFound();
    }

    public function test_the_dead_old_broken_gdpr_route_group_no_longer_exists(): void
    {
        $this->actingAsUser('employee');

        // These used to call GdprController methods that never existed at
        // all (getSARStatus/exportPersonalData/complianceStatus/
        // listRequests/requestSAR/deleteAccount/deletePersonalData) — a
        // guaranteed fatal error on every call, superseded by the real
        // core/gdpr/* group.
        $this->getJson('/api/v1/gdpr/sar-status')->assertNotFound();
        $this->getJson('/api/v1/gdpr/compliance-status')->assertNotFound();
        $this->postJson('/api/v1/gdpr/delete-account')->assertNotFound();
    }

    // ─── Layer 4/9 — Import pipeline, end-to-end via real HTTP ────────────────

    public function test_import_pipeline_uploads_extracts_ai_maps_and_executes_a_real_csv(): void
    {
        $user = $this->actingAsUser('employee');
        Storage::fake('local');

        $csv = "first_name,last_name,email\nJohn,Doe,john@example.test\nJane,Smith,jane@example.test\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $upload = $this->postJson('/api/v1/import/upload', [
            'file' => $file,
            'target_entity' => 'contact',
        ]);
        $upload->assertStatus(202);
        $jobId = $upload->json('id');
        $this->assertNotNull($jobId);

        // With QUEUE_CONNECTION=sync (this app's real driver), the
        // ExtractAndMapImportJob dispatched by upload() already ran inline —
        // before Chantier 32.1's fix this would have fatally errored
        // (BaseAsyncJob had no handle() method).
        $job = ImportJob::find($jobId);
        $this->assertSame('extracted', $job->status);
        $this->assertSame(2, $job->total_rows);
        $this->assertSame(2, $job->rows()->count());

        $show = $this->getJson("/api/v1/import/jobs/{$jobId}");
        $show->assertOk();
        // AiMappingService's heuristic fallback (no AI provider configured
        // in this sandbox) should have already suggested a real mapping —
        // confirming AiMappingService, previously a zero-caller orphan, is
        // now genuinely wired in.
        $this->assertSame('first_name', $show->json('ai_suggestions.column_mapping.first_name'));
        $this->assertSame('email', $show->json('ai_suggestions.column_mapping.email'));

        $mapping = $this->putJson("/api/v1/import/jobs/{$jobId}/mapping", [
            'column_mapping' => [
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'email' => 'email',
            ],
        ]);
        $mapping->assertOk();

        $contactsBefore = \Modules\CRM\Models\Contact::count();

        $execute = $this->postJson("/api/v1/import/jobs/{$jobId}/execute");
        $execute->assertStatus(202);

        // Before Chantier 32.1's fix, ImportExecutorService::executeImport()
        // fatally errored on its very first row via
        // $job->increment('processed_rows') — a column that has never
        // existed on core_import_jobs (real column: processed). Confirm the
        // real ORM-level counters AND the resource's frontend-contract JSON
        // keys both now genuinely reflect real progress.
        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame(2, $job->processed);
        $this->assertSame(0, $job->failed);
        $this->assertSame($contactsBefore + 2, \Modules\CRM\Models\Contact::count());

        $showAfter = $this->getJson("/api/v1/import/jobs/{$jobId}");
        $showAfter->assertOk();
        $this->assertSame(2, $showAfter->json('processed_rows'));
        $this->assertSame(0, $showAfter->json('failed_rows'));
    }

    public function test_import_job_belonging_to_another_user_is_forbidden(): void
    {
        $owner = $this->actingAsUser('employee');
        $job = ImportJob::factory()->create(['user_id' => $owner->id]);

        $this->actingAsUser('employee');

        $this->getJson("/api/v1/import/jobs/{$job->id}")->assertForbidden();
    }

    public function test_import_rollback_deletes_created_records_via_real_route(): void
    {
        $user = $this->actingAsUser('employee');
        Storage::fake('local');

        $csv = "first_name,last_name,email\nRoll,Back,rollback@example.test\n";
        $file = UploadedFile::fake()->createWithContent('rollback.csv', $csv);

        $upload = $this->postJson('/api/v1/import/upload', ['file' => $file, 'target_entity' => 'contact']);
        $jobId = $upload->json('id');

        $this->putJson("/api/v1/import/jobs/{$jobId}/mapping", [
            'column_mapping' => ['first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email'],
        ])->assertOk();
        $this->postJson("/api/v1/import/jobs/{$jobId}/execute")->assertStatus(202);

        $job = ImportJob::find($jobId);
        $this->assertSame(1, $job->processed);
        $recordId = $job->rows()->first()->created_record_id;
        $this->assertNotNull($recordId);
        $this->assertNotNull(\Modules\CRM\Models\Contact::find($recordId));

        $rollback = $this->postJson("/api/v1/import/jobs/{$jobId}/rollback");
        $rollback->assertOk();

        $this->assertNull(\Modules\CRM\Models\Contact::withTrashed()->find($recordId));
    }

    // ─── Layer 4/9 — GDPR self-service jobs, end-to-end via real HTTP ─────────

    public function test_account_export_dispatches_sar_export_job_and_produces_a_real_file(): void
    {
        $user = $this->actingAsUser('employee');
        Storage::fake('local');

        $response = $this->getJson('/api/v1/account/export');

        // Before Chantier 32.1's fix, SarExportJob had no handle() method —
        // dispatch() would have fatally errored with the real sync queue
        // driver instead of returning this 202.
        $response->assertStatus(202);
        Storage::disk('local')->assertExists("sar/{$user->id}/export.json");
    }

    public function test_account_destroy_dispatches_anonymize_job_and_really_anonymizes_the_user(): void
    {
        $user = $this->actingAsUser('employee');
        $user->forceFill(['password' => bcrypt('correct-password')])->save();

        $response = $this->deleteJson('/api/v1/account', ['password' => 'correct-password']);

        $response->assertNoContent();

        // Before Chantier 32.1's fix, AnonymizeUserJob::__construct() called
        // parent::__construct(0) against a base class with no constructor
        // anywhere in its chain — a guaranteed "Cannot call constructor"
        // fatal on every dispatch, before even reaching the missing-
        // handle() bug underneath it.
        $fresh = User::withTrashed()->find($user->id);
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->trashed());
        $this->assertStringContainsString('@deleted.invalid', $fresh->email);
    }

    public function test_account_destroy_rejects_wrong_password(): void
    {
        $user = $this->actingAsUser('employee');
        $user->forceFill(['password' => bcrypt('correct-password')])->save();

        $response = $this->deleteJson('/api/v1/account', ['password' => 'wrong']);

        $response->assertStatus(422);
        $this->assertFalse(User::find($user->id)->trashed());
    }

    public function test_gdpr_delete_account_endpoint_works_without_the_deleted_approval_model(): void
    {
        $user = $this->actingAsUser('employee');

        // Before Chantier 32.1, this referenced Modules\Core\Models\
        // ApprovalInstance — deleted along with the confirmed-dead Core
        // Approval engine — which would now be a class-not-found fatal if
        // left in place.
        $response = $this->postJson('/api/v1/core/gdpr/delete-account');

        $response->assertOk();
        $this->assertTrue(User::withTrashed()->find($user->id)->trashed());
    }

    // ─── Layer 4 — Superadmin tenant-provisioning lifecycle, real HTTP ────────

    public function test_superadmin_can_provision_suspend_reactivate_upgrade_and_purge_a_tenant(): void
    {
        $this->actingAsUser('super-admin');

        $store = $this->postJson('/api/v1/superadmin/tenants', [
            'name' => 'Chantier32 Test Co',
            'country_code' => 'MG',
            'industry' => 'textile',
            'plan' => 'starter',
            'owner_email' => 'chantier32@test.local',
            'owner_name' => 'Chantier32 Owner',
        ]);
        $store->assertCreated();
        $tenantId = $store->json('tenant.id');
        $this->assertNotNull($tenantId);

        // Confirmed real: TenantAuditLog/TenantUser (Chantier 8.6/8.7's own
        // previously-missing-migration fix) actually get written by a real
        // provision() call through the real HTTP route.
        $this->assertGreaterThan(
            0,
            \Modules\Core\Models\TenantAuditLog::where('tenant_id', $tenantId)->count()
        );
        $this->assertGreaterThan(
            0,
            \Modules\Core\Models\TenantUser::where('tenant_id', $tenantId)->count()
        );

        $this->getJson('/api/v1/superadmin/tenants')->assertOk();
        $this->getJson("/api/v1/superadmin/tenants/{$tenantId}")->assertOk();

        $this->postJson("/api/v1/superadmin/tenants/{$tenantId}/suspend", ['reason' => 'test'])
            ->assertOk();
        $this->assertSame('suspended', \Modules\Core\Models\Tenant::find($tenantId)->status);

        $this->postJson("/api/v1/superadmin/tenants/{$tenantId}/reactivate")->assertOk();
        $this->assertSame('active', \Modules\Core\Models\Tenant::find($tenantId)->status);

        $this->postJson("/api/v1/superadmin/tenants/{$tenantId}/upgrade-plan", ['plan' => 'professional'])
            ->assertOk();
        $this->assertSame('professional', \Modules\Core\Models\Tenant::find($tenantId)->plan);

        $this->getJson("/api/v1/superadmin/tenants/{$tenantId}/export")->assertOk();
        $this->getJson('/api/v1/superadmin/stats')->assertOk();
        $this->getJson('/api/v1/superadmin/audit-log')->assertOk();

        $this->deleteJson("/api/v1/superadmin/tenants/{$tenantId}")->assertNoContent();
        $this->assertNull(\Modules\Core\Models\Tenant::find($tenantId));
    }

    public function test_non_super_admin_is_denied_superadmin_routes(): void
    {
        $this->actingAsUser('employee');

        $this->getJson('/api/v1/superadmin/tenants')->assertForbidden();
        $this->postJson('/api/v1/superadmin/tenants', [])->assertForbidden();
    }

    public function test_smart_defaults_endpoints_work_for_any_authenticated_user(): void
    {
        $this->actingAsUser('employee');

        $this->getJson('/api/v1/core/defaults')->assertOk();
        $this->getJson('/api/v1/core/defaults/countries')->assertOk();
        $this->getJson('/api/v1/core/smart-defaults')->assertOk();
    }

    public function test_onboarding_status_resolves_via_the_real_tenantuser_pivot_for_the_acting_user(): void
    {
        $user = $this->actingAsUser('employee');
        $tenant = \Modules\Core\Models\Tenant::factory()->create();
        \Modules\Core\Models\TenantUser::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

        $this->getJson('/api/v1/onboarding/status')->assertOk();
        $this->postJson('/api/v1/onboarding/step/1', ['data' => ['done' => true]])->assertOk();
        $this->postJson('/api/v1/onboarding/skip')->assertOk();
    }

    // ─── Layer 4 — Offline sync (real consumer: AppLayout's OfflineIndicator/
    // OfflineStatusPill → resources/js/stores/sync.js) ─────────────────────────

    public function test_sync_push_and_pull_round_trip_over_real_http(): void
    {
        $user = $this->actingAsUser('employee');

        $push = $this->postJson('/api/v1/sync/push', [
            'mutations' => [[
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'entity_type' => 'crm_contact',
                'operation' => 'create',
                'payload' => ['first_name' => 'Sync', 'last_name' => 'Test', 'owner_id' => $user->id],
                'client_timestamp' => now()->toIso8601String(),
            ]],
        ]);
        $push->assertOk();
        $push->assertJson(['applied' => 1, 'failed' => 0]);

        $pull = $this->getJson('/api/v1/sync/pull?'.http_build_query(['last_sync_at' => now()->subMinute()->toIso8601String()]));
        $pull->assertOk();
        $this->assertNotEmpty($pull->json('changes'));
    }

    // ─── Layer 4/6 — Sandboxes: real admin page, previously always-empty ──────

    public function test_sandbox_index_lists_real_sandboxes_without_a_parent_tenant_id_filter(): void
    {
        $this->actingAsUser('super-admin');

        $parent = \Modules\Core\Models\Tenant::factory()->create();
        $sandboxTenant = \Modules\Core\Models\Tenant::factory()->create();
        \Modules\Core\Models\Sandbox::create([
            'tenant_id' => $sandboxTenant->id,
            'parent_tenant_id' => $parent->id,
            'name' => 'Test Sandbox',
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        // Before Chantier 32.1's fix, this defaulted to the phantom
        // users.tenant_id column (always null → matched as an empty
        // string), so the real admin page always listed zero sandboxes
        // regardless of how many actually existed.
        $response = $this->getJson('/api/v1/core/sandboxes');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_onboarding_status_cannot_be_spoofed_via_a_client_controlled_tenant_header(): void
    {
        // Confirmed cross-tenant IDOR before the fix: resolveTenant() read
        // a client-supplied ?tenant_id=/X-Tenant-Id header BEFORE ever
        // consulting the real TenantUser pivot, so any authenticated user
        // with no real tenant of their own could read — and, worse, WRITE
        // via onboarding/step and onboarding/skip — another tenant's
        // onboarding progress just by naming its id.
        $victimTenant = \Modules\Core\Models\Tenant::factory()->create();

        $this->actingAsUser('employee');

        $this->withHeaders(['X-Tenant-Id' => (string) $victimTenant->id])
            ->getJson('/api/v1/onboarding/status')
            ->assertNotFound();

        $this->getJson('/api/v1/onboarding/status?tenant_id='.$victimTenant->id)
            ->assertNotFound();

        $this->withHeaders(['X-Tenant-Id' => (string) $victimTenant->id])
            ->postJson('/api/v1/onboarding/skip')
            ->assertNotFound();
    }
}
