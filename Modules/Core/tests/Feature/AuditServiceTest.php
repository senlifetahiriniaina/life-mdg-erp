<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\GdprConsent;
use Modules\Core\Services\AuditService;
use Tests\TestCase;

/**
 * AuditServiceTest — comprehensive tests for the AuditService.
 *
 * Covers: log(), logLogin(), logLoginFailed(), logLogout(), logCreate(),
 * logUpdate(), logDelete(), logExport(), getUserActivity(),
 * getSubjectHistory(), getStats(), AuditLog model helpers.
 */
class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AuditService::class);
        $this->user    = User::factory()->create(['name' => 'Test User']);
    }

    // ─── log() ───────────────────────────────────────────────────────────────

    public function test_log_stores_record_with_required_fields(): void
    {
        $log = $this->service->log(
            action: 'custom_action',
            userId: $this->user->id,
            module: 'Core',
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals('custom_action', $log->action);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals('Core', $log->module);
        $this->assertDatabaseHas('core_audit_logs', ['action' => 'custom_action']);
    }

    public function test_log_with_description_stores_description(): void
    {
        $log = $this->service->log(
            action: 'noted_action',
            userId: $this->user->id,
            description: 'Something notable happened',
        );

        $this->assertEquals('Something notable happened', $log->description);
    }

    public function test_log_with_old_and_new_values(): void
    {
        $log = $this->service->log(
            action: 'updated',
            userId: $this->user->id,
            oldValues: ['name' => 'Old Name'],
            newValues: ['name' => 'New Name'],
        );

        $this->assertEquals(['name' => 'Old Name'], $log->old_values);
        $this->assertEquals(['name' => 'New Name'], $log->new_values);
    }

    public function test_log_with_no_user_id_stores_null(): void
    {
        $log = $this->service->log(action: 'anonymous_action');

        $this->assertNull($log->user_id);
    }

    // ─── logLogin() / logLoginFailed() / logLogout() ─────────────────────────

    public function test_log_login_creates_login_record(): void
    {
        $log = $this->service->logLogin($this->user->id);

        $this->assertEquals('login', $log->action);
        $this->assertEquals('Auth', $log->module);
        $this->assertEquals($this->user->id, $log->user_id);
    }

    public function test_log_login_failed_stores_email_as_user_name(): void
    {
        $log = $this->service->logLoginFailed('badguy@example.com');

        $this->assertEquals('login_failed', $log->action);
        $this->assertEquals('badguy@example.com', $log->user_name);
        $this->assertNull($log->user_id);
    }

    public function test_log_logout_creates_logout_record(): void
    {
        $log = $this->service->logLogout($this->user->id);

        $this->assertEquals('logout', $log->action);
        $this->assertEquals('Auth', $log->module);
    }

    // ─── logCreate() / logUpdate() / logDelete() ─────────────────────────────

    public function test_log_create_sets_action_to_created(): void
    {
        $consent = GdprConsent::factory()->create(['user_id' => $this->user->id]);

        $log = $this->service->logCreate($this->user->id, $consent, module: 'Core');

        $this->assertEquals('created', $log->action);
        $this->assertEquals(GdprConsent::class, $log->subject_type);
        $this->assertEquals($consent->id, $log->subject_id);
    }

    public function test_log_update_captures_old_and_new_values(): void
    {
        $consent = GdprConsent::factory()->create(['user_id' => $this->user->id]);
        $oldValues = ['granted' => false];

        $consent->update(['granted' => true]);
        $log = $this->service->logUpdate($this->user->id, $consent, $oldValues, module: 'Core');

        $this->assertEquals('updated', $log->action);
        $this->assertEquals(['granted' => false], $log->old_values);
    }

    public function test_log_delete_captures_old_values(): void
    {
        $consent = GdprConsent::factory()->create(['user_id' => $this->user->id]);
        $log = $this->service->logDelete($this->user->id, $consent, module: 'Core');

        $this->assertEquals('deleted', $log->action);
        $this->assertNotNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    public function test_log_export_creates_export_record(): void
    {
        $log = $this->service->logExport($this->user->id, 'customers', module: 'CRM');

        $this->assertEquals('export', $log->action);
        $this->assertStringContainsString('customers', $log->description ?? '');
    }

    // ─── getUserActivity() ────────────────────────────────────────────────────

    public function test_get_user_activity_returns_user_logs(): void
    {
        $other = User::factory()->create();

        $this->service->logLogin($this->user->id);
        $this->service->logLogout($this->user->id);
        $this->service->logLogin($other->id);

        $activity = $this->service->getUserActivity($this->user->id);

        $this->assertEquals(2, $activity->count());
        $activity->each(fn ($log) => $this->assertEquals($this->user->id, $log->user_id));
    }

    public function test_get_user_activity_respects_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->service->logLogin($this->user->id);
        }

        $activity = $this->service->getUserActivity($this->user->id, limit: 5);

        $this->assertLessThanOrEqual(5, $activity->count());
    }

    // ─── getSubjectHistory() ─────────────────────────────────────────────────

    public function test_get_subject_history_returns_logs_for_subject(): void
    {
        $consent = GdprConsent::factory()->create(['user_id' => $this->user->id]);

        $this->service->logCreate($this->user->id, $consent, module: 'Core');
        $this->service->logUpdate($this->user->id, $consent, ['granted' => false], module: 'Core');

        $history = $this->service->getSubjectHistory(GdprConsent::class, $consent->id);

        $this->assertEquals(2, $history->count());
        $history->each(fn ($log) => $this->assertEquals($consent->id, $log->subject_id));
    }

    // ─── getStats() ──────────────────────────────────────────────────────────

    public function test_get_stats_returns_correct_total(): void
    {
        $this->service->logLogin($this->user->id);
        $this->service->logLogout($this->user->id);
        $this->service->logLoginFailed('x@y.com');

        $stats = $this->service->getStats();

        $this->assertGreaterThanOrEqual(3, $stats['total_logs']);
        $this->assertArrayHasKey('by_action', $stats);
        $this->assertArrayHasKey('by_module', $stats);
        $this->assertArrayHasKey('top_users', $stats);
    }

    public function test_get_stats_filtered_by_user(): void
    {
        $other = User::factory()->create();

        $this->service->logLogin($this->user->id);
        $this->service->logLogin($other->id);

        $stats = $this->service->getStats(['user_id' => $this->user->id]);

        $this->assertEquals(1, $stats['total_logs']);
    }

    // ─── AuditLog Model helpers ───────────────────────────────────────────────

    public function test_audit_log_is_create_helper(): void
    {
        $log = $this->service->logCreate($this->user->id, GdprConsent::factory()->create(['user_id' => $this->user->id]));

        $this->assertTrue($log->isCreate());
        $this->assertFalse($log->isUpdate());
        $this->assertFalse($log->isDelete());
    }

    public function test_audit_log_has_value_changes(): void
    {
        $log = $this->service->log(
            action: 'updated',
            userId: $this->user->id,
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'published'],
        );

        $this->assertTrue($log->hasValueChanges());
    }

    public function test_audit_log_get_changed_fields(): void
    {
        $log = $this->service->log(
            action: 'updated',
            userId: $this->user->id,
            oldValues: ['status' => 'draft', 'name' => 'Same'],
            newValues: ['status' => 'published', 'name' => 'Same'],
        );

        $changed = $log->getChangedFields();

        $this->assertContains('status', $changed);
        $this->assertNotContains('name', $changed);
    }

    public function test_audit_log_user_relationship(): void
    {
        $log = $this->service->logLogin($this->user->id);

        $this->assertNotNull($log->user);
        $this->assertEquals($this->user->id, $log->user->id);
    }

    public function test_audit_log_infers_module_from_model_namespace(): void
    {
        $consent = GdprConsent::factory()->create(['user_id' => $this->user->id]);
        // logCreate without explicit module — should infer 'Core' from namespace
        $log = $this->service->logCreate($this->user->id, $consent);

        $this->assertEquals('Core', $log->module);
    }
}
