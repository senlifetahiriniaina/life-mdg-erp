<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\DataRequest;
use Modules\Core\Models\GdprConsent;
use Modules\Core\Services\GdprService;
use Tests\TestCase;

/**
 * GdprServiceTest — tests for GDPR consent & data-request lifecycle.
 *
 * Covers: recordConsent, revokeConsent, getUserConsents, hasConsent,
 * createDataRequest, processDeletionRequest, getPendingRequests,
 * getOverdueRequests, getDashboardStats, getConsentBreakdown.
 */
class GdprServiceTest extends TestCase
{
    use RefreshDatabase;

    private GdprService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GdprService::class);
        $this->user    = User::factory()->create();
    }

    // ─── recordConsent() ─────────────────────────────────────────────────────

    public function test_record_consent_creates_new_entry(): void
    {
        $consent = $this->service->recordConsent([
            'user_id'      => $this->user->id,
            'consent_type' => 'marketing',
            'granted'      => true,
            'granted_at'   => now(),
            'email'        => $this->user->email,
        ]);

        $this->assertInstanceOf(GdprConsent::class, $consent);
        $this->assertDatabaseHas('core_gdpr_consents', [
            'user_id'      => $this->user->id,
            'consent_type' => 'marketing',
        ]);
    }

    public function test_record_consent_upserts_existing_entry(): void
    {
        $this->service->recordConsent([
            'user_id'      => $this->user->id,
            'consent_type' => 'analytics',
            'granted'      => false,
            'email'        => $this->user->email,
        ]);

        $this->service->recordConsent([
            'user_id'      => $this->user->id,
            'consent_type' => 'analytics',
            'granted'      => true,
            'email'        => $this->user->email,
            'granted_at'   => now(),
        ]);

        $this->assertEquals(1, GdprConsent::where('user_id', $this->user->id)
            ->where('consent_type', 'analytics')->count());
    }

    // ─── revokeConsent() ─────────────────────────────────────────────────────

    public function test_revoke_consent_sets_revoked_at(): void
    {
        GdprConsent::factory()->granted()->create([
            'user_id'      => $this->user->id,
            'consent_type' => 'marketing',
        ]);

        $result = $this->service->revokeConsent($this->user->id, 'marketing');

        $this->assertTrue($result);
        $consent = GdprConsent::where('user_id', $this->user->id)
            ->where('consent_type', 'marketing')->first();
        $this->assertNotNull($consent->revoked_at);
    }

    public function test_revoke_consent_returns_false_when_not_found(): void
    {
        $result = $this->service->revokeConsent($this->user->id, 'nonexistent');

        $this->assertFalse($result);
    }

    // ─── getUserConsents() ────────────────────────────────────────────────────

    public function test_get_user_consents_returns_all_types(): void
    {
        GdprConsent::factory()->create(['user_id' => $this->user->id, 'consent_type' => 'marketing']);
        GdprConsent::factory()->create(['user_id' => $this->user->id, 'consent_type' => 'analytics']);

        $consents = $this->service->getUserConsents($this->user->id);

        $this->assertEquals(2, $consents->count());
    }

    public function test_get_user_consents_excludes_other_users(): void
    {
        $other = User::factory()->create();
        GdprConsent::factory()->create(['user_id' => $this->user->id, 'consent_type' => 'marketing']);
        GdprConsent::factory()->create(['user_id' => $other->id, 'consent_type' => 'analytics']);

        $consents = $this->service->getUserConsents($this->user->id);

        $this->assertEquals(1, $consents->count());
    }

    // ─── hasConsent() ─────────────────────────────────────────────────────────

    public function test_has_consent_returns_true_when_granted(): void
    {
        GdprConsent::factory()->granted()->create([
            'user_id'      => $this->user->id,
            'consent_type' => 'functional',
        ]);

        $this->assertTrue($this->service->hasConsent($this->user->id, 'functional'));
    }

    public function test_has_consent_returns_false_when_revoked(): void
    {
        GdprConsent::factory()->revoked()->create([
            'user_id'      => $this->user->id,
            'consent_type' => 'marketing',
        ]);

        $this->assertFalse($this->service->hasConsent($this->user->id, 'marketing'));
    }

    public function test_has_consent_returns_false_when_not_exists(): void
    {
        $this->assertFalse($this->service->hasConsent($this->user->id, 'necessary'));
    }

    // ─── createDataRequest() ─────────────────────────────────────────────────

    public function test_create_data_request_sets_pending_status(): void
    {
        $request = $this->service->createDataRequest([
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'request_type' => 'export',
        ]);

        $this->assertInstanceOf(DataRequest::class, $request);
        $this->assertEquals('pending', $request->status);
        $this->assertTrue($request->isPending());
    }

    public function test_create_data_request_stores_request_type(): void
    {
        $request = $this->service->createDataRequest([
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'request_type' => 'deletion',
        ]);

        $this->assertEquals('deletion', $request->request_type);
        $this->assertDatabaseHas('core_data_requests', [
            'user_id'      => $this->user->id,
            'request_type' => 'deletion',
        ]);
    }

    // ─── processDeletionRequest() ─────────────────────────────────────────────

    public function test_process_deletion_request_soft_deletes_user(): void
    {
        $target = User::factory()->create();
        $request = DataRequest::factory()->create([
            'user_id'      => $target->id,
            'email'        => $target->email,
            'request_type' => 'deletion',
            'status'       => 'pending',
            'requested_at' => now(),
        ]);

        $this->service->processDeletionRequest($request);

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_process_deletion_request_marks_request_completed(): void
    {
        $target = User::factory()->create();
        $request = DataRequest::factory()->create([
            'user_id'      => $target->id,
            'email'        => $target->email,
            'request_type' => 'deletion',
            'status'       => 'pending',
            'requested_at' => now(),
        ]);

        $this->service->processDeletionRequest($request);

        $request->refresh();
        $this->assertEquals('completed', $request->status);
    }

    // ─── getPendingRequests() ─────────────────────────────────────────────────

    public function test_get_pending_requests_excludes_completed(): void
    {
        DataRequest::factory()->create([
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'request_type' => 'export',
            'status'       => 'pending',
            'requested_at' => now(),
        ]);
        DataRequest::factory()->create([
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'request_type' => 'export',
            'status'       => 'completed',
            'requested_at' => now(),
        ]);

        $pending = $this->service->getPendingRequests();

        $this->assertEquals(1, $pending->count());
    }

    // ─── getOverdueRequests() ─────────────────────────────────────────────────

    public function test_get_overdue_requests_returns_old_pending(): void
    {
        // Overdue: 31 days ago
        DataRequest::factory()->create([
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'request_type' => 'export',
            'status'       => 'pending',
            'requested_at' => now()->subDays(31),
        ]);
        // Not overdue: today
        DataRequest::factory()->create([
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'request_type' => 'export',
            'status'       => 'pending',
            'requested_at' => now(),
        ]);

        $overdue = $this->service->getOverdueRequests();

        $this->assertEquals(1, $overdue->count());
    }

    // ─── getDashboardStats() ─────────────────────────────────────────────────

    public function test_get_dashboard_stats_returns_expected_keys(): void
    {
        $stats = $this->service->getDashboardStats();

        $this->assertArrayHasKey('total_consents', $stats);
        $this->assertArrayHasKey('granted_consents', $stats);
        $this->assertArrayHasKey('revoked_consents', $stats);
        $this->assertArrayHasKey('consent_rate', $stats);
        $this->assertArrayHasKey('pending_requests', $stats);
        $this->assertArrayHasKey('overdue_requests', $stats);
        $this->assertArrayHasKey('completed_requests', $stats);
    }

    public function test_get_dashboard_stats_calculates_consent_rate(): void
    {
        // 2 granted, 0 revoked out of 2 total => 100%
        GdprConsent::factory()->granted()->create(['user_id' => $this->user->id, 'consent_type' => 'marketing']);
        GdprConsent::factory()->granted()->create(['user_id' => $this->user->id, 'consent_type' => 'analytics']);

        $stats = $this->service->getDashboardStats();

        $this->assertEquals(100.0, $stats['consent_rate']);
    }

    // ─── getConsentBreakdown() ────────────────────────────────────────────────

    public function test_get_consent_breakdown_returns_all_types(): void
    {
        $breakdown = $this->service->getConsentBreakdown();

        $types = array_column($breakdown, 'type');
        $this->assertContains('marketing', $types);
        $this->assertContains('analytics', $types);
        $this->assertContains('functional', $types);
        $this->assertContains('necessary', $types);
    }

    // ─── DataRequest model helpers ────────────────────────────────────────────

    public function test_data_request_is_overdue_helper(): void
    {
        $request = DataRequest::factory()->create([
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'request_type' => 'export',
            'status'       => 'pending',
            'requested_at' => now()->subDays(31),
        ]);

        $this->assertTrue($request->isOverdue());
    }

    public function test_data_request_reject_updates_status(): void
    {
        $request = DataRequest::factory()->create([
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'request_type' => 'export',
            'status'       => 'pending',
            'requested_at' => now(),
        ]);

        $request->reject('Insufficient information provided');

        $this->assertEquals('rejected', $request->fresh()->status);
        $this->assertStringContainsString('Insufficient', $request->fresh()->admin_notes ?? '');
    }
}
