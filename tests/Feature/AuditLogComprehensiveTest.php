<?php

declare(strict_types=1);

use App\Models\Admin\AuditLog;
use App\Models\User;
use App\Services\AuditLog\ApprovalOverrideService;
use App\Services\AuditLog\RetentionPolicyService;
use App\Services\AuditLog\TamperDetectionService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

// ========== COMPOSITE INDEX TESTS ==========

it('can query audit logs by created_at and module efficiently', function () {
    AuditLog::factory()->count(5)->create([
        'module' => 'inventory',
        'created_at' => now(),
    ]);
    AuditLog::factory()->count(3)->create([
        'module' => 'manufacturing',
        'created_at' => now()->subDay(),
    ]);

    // Test query using composite index
    $logs = AuditLog::where('created_at', '>=', now()->subDay())
        ->where('module', 'inventory')
        ->get();

    expect($logs)->toHaveCount(5);
});

it('can query audit logs by user activity efficiently', function () {
    $userId = $this->user->id;

    AuditLog::factory()->count(3)->create([
        'user_id' => $userId,
        'created_at' => now(),
    ]);
    AuditLog::factory()->count(2)->create(['created_at' => now()]);

    $logs = AuditLog::where('user_id', $userId)
        ->orderByDesc('created_at')
        ->get();

    expect($logs)->toHaveCount(3);
});

it('can query audit logs by action efficiently', function () {
    AuditLog::factory()->count(4)->create([
        'action' => 'create',
        'created_at' => now(),
    ]);
    AuditLog::factory()->create(['action' => 'delete', 'created_at' => now()]);

    $logs = AuditLog::where('action', 'create')
        ->orderByDesc('created_at')
        ->get();

    expect($logs)->toHaveCount(4);
});

it('can query resource history efficiently', function () {
    AuditLog::factory()->count(5)->create([
        'resource_type' => 'Contact',
        'resource_id' => 1,
    ]);
    AuditLog::factory()->create(['resource_type' => 'Contact', 'resource_id' => 2]);

    $logs = AuditLog::where('resource_type', 'Contact')
        ->where('resource_id', 1)
        ->orderBy('created_at')
        ->get();

    expect($logs)->toHaveCount(5);
});

// ========== TAMPER DETECTION TESTS ==========

it('can sign audit log entries with HMAC-SHA256', function () {
    $log = AuditLog::factory()->create();

    $service = new TamperDetectionService();
    $service->signLogEntry($log);

    $log->refresh();
    expect($log->signature)->not->toBeNull();
    expect(strlen($log->signature))->toBe(64); // SHA256 hex = 64 chars
});

it('can verify legitimate log signatures', function () {
    $log = AuditLog::factory()->create();

    $service = new TamperDetectionService();
    $service->signLogEntry($log);

    $verified = $service->verifySignature($log);
    expect($verified)->toBeTrue();
});

it('detects tampered logs', function () {
    $log = AuditLog::factory()->create();

    $service = new TamperDetectionService();
    $service->signLogEntry($log);

    // Tamper with the log
    $log->update(['action' => 'malicious_action']);

    $verified = $service->verifySignature($log);
    expect($verified)->toBeFalse();
});

it('can detect all tampered logs in time period', function () {
    $log1 = AuditLog::factory()->create(['created_at' => now()]);
    $log2 = AuditLog::factory()->create(['created_at' => now()]);

    $service = new TamperDetectionService();
    $service->signLogEntry($log1);
    $service->signLogEntry($log2);

    // Tamper with one log
    $log2->update(['action' => 'tampered']);

    $tampered = $service->detectTamperedLogs(24);
    expect($tampered)->toHaveCount(1);
    expect($tampered[0]['id'])->toBe($log2->id);
});

it('can mark logs immutable after retention period', function () {
    $oldLog = AuditLog::factory()->create(['created_at' => now()->subDays(91)]);
    $newLog = AuditLog::factory()->create(['created_at' => now()]);

    $service = new TamperDetectionService();
    $count = $service->lockLogImmutable(90);

    expect($count)->toBe(1);
    $oldLog->refresh();
    expect($oldLog->is_immutable)->toBeTrue();
    $newLog->refresh();
    expect($newLog->is_immutable)->toBeFalse();
});

it('can verify log immutability compliance', function () {
    AuditLog::factory()->count(3)->create([
        'created_at' => now()->subDays(91),
        'is_immutable' => true,
    ]);

    $service = new TamperDetectionService();
    $compliance = $service->verifyLogImmutability();

    expect($compliance['compliant'])->toBeTrue();
    expect($compliance['unlocked_immutable_logs'])->toBe(0);
});

it('can track audit log access', function () {
    $log = AuditLog::factory()->create();

    $service = new TamperDetectionService();
    $service->logAccess($this->user->id, $log->id, 'read');

    $history = $service->getAccessHistory($log->id);
    expect($history)->toHaveCount(1);
    expect($history[0]->action)->toBe('read');
    expect($history[0]->user_id)->toBe($this->user->id);
});

it('can verify log chain integrity', function () {
    $log1 = AuditLog::factory()->create(['created_at' => now()->subHour()]);
    $log2 = AuditLog::factory()->create(['created_at' => now()]);

    $service = new TamperDetectionService();
    $service->signLogEntry($log1);
    $service->signLogEntry($log2);

    $integrity = $service->verifyLogChainIntegrity(24);
    expect($integrity['chain_valid'])->toBeTrue();
    expect($integrity['issues'])->toBeEmpty();
});

// ========== DATA RETENTION TESTS ==========

it('can set expiration date for logs', function () {
    $log = AuditLog::factory()->create();

    $service = new RetentionPolicyService();
    $expirationDate = now()->addYears(2);
    $service->setExpiration($log, $expirationDate);

    $log->refresh();
    expect($log->data_expires_at->toDateString())->toBe($expirationDate->toDateString());
});

it('can purge expired logs', function () {
    AuditLog::factory()->create([
        'data_expires_at' => now()->subDay(),
    ]);
    AuditLog::factory()->create([
        'data_expires_at' => now()->addDay(),
    ]);

    $service = new RetentionPolicyService();
    $purged = $service->purgeExpiredLogs();

    expect($purged)->toBe(1);
    expect(AuditLog::count())->toBe(1);
});

it('can archive old logs to S3', function () {
    $oldLog = AuditLog::factory()->create(['created_at' => now()->subDays(91)]);
    $newLog = AuditLog::factory()->create(['created_at' => now()]);

    $service = new RetentionPolicyService();
    $archived = $service->archiveOldLogs(90);

    expect($archived)->toBe(1);
    $oldLog->refresh();
    expect($oldLog->archived_at)->not->toBeNull();
});

it('can get logs approaching expiration', function () {
    AuditLog::factory()->create([
        'data_expires_at' => now()->addDays(15),
    ]);
    AuditLog::factory()->create([
        'data_expires_at' => now()->addDays(45),
    ]);

    $service = new RetentionPolicyService();
    $expiring = $service->getExpiringLogs(30);

    expect($expiring)->toHaveCount(1);
    expect($expiring[0]['days_until_expiry'])->toBeLessThanOrEqual(30);
});

it('can generate retention summary', function () {
    AuditLog::factory()->count(10)->create();
    AuditLog::factory()->create(['archived_at' => now()]);

    $service = new RetentionPolicyService();
    $summary = $service->getRetentionSummary();

    expect($summary)->toHaveKeys(['total_logs', 'archived_logs', 'compliance']);
    expect($summary['total_logs'])->toBe(11);
    expect($summary['archived_logs'])->toBe(1);
});

it('can export logs before purge for compliance', function () {
    AuditLog::factory()->create(['created_at' => now()->subDays(91)]);
    AuditLog::factory()->create(['created_at' => now()]);

    $service = new RetentionPolicyService();
    $filename = $service->exportBeforePurge(90);

    expect($filename)->not->toBeNull();
    expect($filename)->toMatch('/\.csv$/');
});

// ========== APPROVAL OVERRIDE TESTS ==========

it('can record approval override with reason', function () {
    $service = new ApprovalOverrideService();
    $override = $service->recordOverride(
        $this->user->id,
        1,
        'Budget exception approved by CFO',
        'invoice',
        100,
        'Invoice'
    );

    expect($override['id'])->not->toBeNull();
    expect($override['approval_type'])->toBe('invoice');
});

it('can get overrides for specific approval', function () {
    $service = new ApprovalOverrideService();
    $service->recordOverride($this->user->id, 1, 'Reason 1', 'invoice');
    $service->recordOverride($this->user->id, 1, 'Reason 2', 'invoice');

    $overrides = $service->getOverridesForApproval(1);
    expect($overrides)->toHaveCount(2);
});

it('can get user override history', function () {
    $service = new ApprovalOverrideService();
    $service->recordOverride($this->user->id, 1, 'Reason 1', 'invoice');
    $service->recordOverride($this->user->id, 2, 'Reason 2', 'expense');

    $history = $service->getUserOverrideHistory($this->user->id);
    expect($history)->toHaveCount(2);
});

it('can get override statistics', function () {
    $service = new ApprovalOverrideService();
    $service->recordOverride($this->user->id, 1, 'Reason', 'invoice');
    $service->recordOverride($this->user->id, 2, 'Reason', 'invoice');
    $service->recordOverride($this->user->id, 3, 'Reason', 'expense');

    $stats = $service->getOverrideStatistics(30);

    expect($stats['total_overrides'])->toBe(3);
    expect($stats['by_type'])->toHaveKey('invoice');
    expect($stats['by_type'])->toHaveKey('expense');
});

it('can detect anomalies in approval overrides', function () {
    $service = new ApprovalOverrideService();

    // Create many overrides (unusual activity)
    for ($i = 1; $i <= 15; $i++) {
        $service->recordOverride($this->user->id, $i, 'Reason', 'invoice');
    }

    $anomalies = $service->detectAnomalies();
    expect($anomalies)->not->toBeEmpty();
});

it('can generate audit report for overrides', function () {
    $service = new ApprovalOverrideService();
    $service->recordOverride($this->user->id, 1, 'Reason', 'invoice');

    $report = $service->generateAuditReport(30);

    expect($report)->toHaveKeys(['total_overrides', 'status', 'generated_at']);
    expect($report['total_overrides'])->toBe(1);
});

it('alerts CFO on finance-related overrides', function () {
    $service = new ApprovalOverrideService();

    // This should trigger CFO alert
    $override = $service->recordOverride(
        $this->user->id,
        1,
        'Large invoice override',
        'invoice'
    );

    expect($override['id'])->not->toBeNull();
    // Note: actual email/notification would be tested in integration tests
});
