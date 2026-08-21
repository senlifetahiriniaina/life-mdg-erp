<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Chantier 32.4 — 14-layer deep audit of Modules/AuditLog.
 *
 * Headline finding: this module holds two entirely different concerns.
 *   1. A real, live, essential one — AuditLogApiController/AuditLogWebController
 *      (browsing the app's real audit trail, Modules\Core\Models\AuditLog /
 *      `core_audit_logs`) and AuditLogAiAssistController — all correctly
 *      authorized, tested against the real HTTP route, and (after this
 *      chantier) correctly wired to the real UI page.
 *   2. A confirmed-dead one — Modules\AuditLog\Models\AuditLog / `audit_logs`,
 *      its own AuditService, and its only real writer (App\Listeners\
 *      AuthEventSubscriber) — deleted this chantier. See the drop migration's
 *      own docblock for the full empirical trail (zero readers anywhere;
 *      its one real writer duplicated Modules\Core\Listeners\AuditAuthListener
 *      with a real bug, tenant_id hardcoded to 0 on every row).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function chantier32AuditLogUser(array $permissions = ['auditlog.logs.view']): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
    }

    return $user;
}

// ─── Layer 9 (fake/dead) — the dead duplicate subsystem is genuinely gone ──────

test('the dead duplicate audit_logs table no longer exists', function () {
    expect(Schema::hasTable('audit_logs'))->toBeFalse();
});

test('the real, live core_audit_logs table still exists and is untouched', function () {
    expect(Schema::hasTable('core_audit_logs'))->toBeTrue();
});

test('Modules\AuditLog\Models\AuditLog and its AuditService no longer exist', function () {
    expect(class_exists(\Modules\AuditLog\Models\AuditLog::class))->toBeFalse()
        ->and(class_exists(\Modules\AuditLog\Services\AuditService::class))->toBeFalse();
});

test('App\Listeners\AuthEventSubscriber (the dead table\'s only real writer) no longer exists', function () {
    expect(class_exists(\App\Listeners\AuthEventSubscriber::class))->toBeFalse();
});

// ─── Regression: the REAL audit trail still works after removing its dead ──────
// ─── duplicate — a real login event still lands correctly in core_audit_logs ──

test('a real login event still writes correctly to the real core_audit_logs table (AuditAuthListener untouched)', function () {
    $user = chantier32AuditLogUser();

    $before = \Modules\Core\Models\AuditLog::where('user_id', $user->id)->where('event_type', 'login')->count();

    event(new \Illuminate\Auth\Events\Login('sanctum', $user, false));

    $after = \Modules\Core\Models\AuditLog::where('user_id', $user->id)->where('event_type', 'login')->count();
    expect($after)->toBe($before + 1);

    $entry = \Modules\Core\Models\AuditLog::where('user_id', $user->id)->where('event_type', 'login')->latest()->first();
    expect((int) $entry->company_id)->toBe((int) ($user->company_id ?? 0));
});

// ─── Regression: HasAuditLog trait's real file-based trail (~200 models) ──────
// ─── still works after removing its dead auditLogs() relation ─────────────────

test('HasAuditLog trait still writes real create/update/delete entries to the audit log channel', function () {
    $logPath = storage_path('logs/audit-' . now()->format('Y-m-d') . '.log');
    @unlink($logPath);

    $setting = \Modules\Settings\Models\Setting::factory()->create();

    expect(file_exists($logPath))->toBeTrue();
    $contents = file_get_contents($logPath);
    expect($contents)->toContain('[created] Setting')
        ->and($contents)->toContain((string) $setting->id);

    @unlink($logPath);
});

test('HasAuditLog trait no longer exposes the dead, broken auditLogs() relation', function () {
    expect(method_exists(\Modules\Settings\Models\Setting::class, 'auditLogs'))->toBeFalse();
});

// ─── Layer 7 (RBAC) — a real permission-denial test was missing entirely ──────
// ─── (only "unauthenticated -> 401" was covered anywhere in this module) ──────

test('GET /api/v1/audit-logs is denied (403) for a user without auditlog.logs.view', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden();
});

test('GET /api/v1/audit-logs/export is denied (403) for a user with view but not export', function () {
    $user = chantier32AuditLogUser(['auditlog.logs.view']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit-logs/export')
        ->assertForbidden();
});

test('GET /audit/logs (web) is denied for a user without auditlog.logs.view-any', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/audit/logs')
        ->assertForbidden();
});

// ─── Layer 3/13 — the real web page now actually calls the real AI-assist ─────
// ─── endpoint (it never did before this chantier) ──────────────────────────────

test('GET /audit/logs renders the real Inertia page with the props the real Vue component reads', function () {
    $user = chantier32AuditLogUser(['auditlog.logs.view-any']);

    $response = $this->actingAs($user)->get('/audit/logs');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('AuditLog/Index')
        ->has('logs')
        ->has('stats')
        ->has('modules')
        ->has('eventTypes')
        ->has('filters')
    );
});

// ─── Layer 13 (IA) — real, UNMOCKED fallback guidance for AuditLog's 3 real ───
// ─── registered actions (no mock in this file, unlike AuditLogRoutesTest.php) ─

test('real fallback guidance for AuditLog.view_audit_log is non-empty in French and English', function () {
    $user = chantier32AuditLogUser();

    foreach (['fr', 'en'] as $locale) {
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/audit-logs/ai/assist', ['action' => 'view_audit_log', 'locale' => $locale])
            ->assertOk();

        expect($response->json('what_to_do'))->not->toBeEmpty();
        expect($response->json('how_to_do'))->not->toBeEmpty();
        expect($response->json('enabled'))->toBeFalse(); // no ANTHROPIC_API_KEY in this sandbox — static fallback path
    }
});

test('real fallback guidance for AuditLog.export_audit and AuditLog.filter_events is non-empty', function () {
    $user = chantier32AuditLogUser();

    foreach (['export_audit', 'filter_events'] as $action) {
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/audit-logs/ai/assist', ['action' => $action, 'locale' => 'fr'])
            ->assertOk();

        expect($response->json('what_to_do'))->not->toBeEmpty("Empty fallback for AuditLog.{$action}");
    }
});

test('AuditLog is genuinely registered in AiContextualAssistantService::supportedModules()', function () {
    $modules = app(\Modules\AI\Services\AiContextualAssistantService::class)->supportedModules();

    expect($modules)->toHaveKey('AuditLog')
        ->and($modules['AuditLog'])->toContain('view_audit_log', 'export_audit', 'filter_events');
});

// ─── Layer 5/10 (format de données / relational) — the real controllers' ──────
// ─── model shape genuinely matches the live core_audit_logs schema ────────────

test('core_audit_logs columns the real controllers query all exist on the live schema', function () {
    $columns = Schema::getColumnListing('core_audit_logs');

    foreach (['id', 'user_id', 'company_id', 'user_name', 'module', 'action', 'event_type', 'description', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at'] as $expected) {
        expect($columns)->toContain($expected);
    }
});

// ─── Layer 14f (performance) — index()/export()/stats() stay N+1-free under ──
// ─── a realistic volume, and complete in a sane amount of time ────────────────

test('index/stats/export stay fast and N+1-free under a realistic volume of audit log rows', function () {
    $user = chantier32AuditLogUser(['auditlog.logs.view', 'auditlog.logs.export']);

    \Modules\Core\Models\AuditLog::factory()->count(500)->create([
        'company_id' => $user->company_id ?? 0,
        'module'     => 'CRM',
        'event_type' => 'model_created',
    ]);

    \Illuminate\Support\Facades\DB::enableQueryLog();

    $start = microtime(true);
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/audit-logs')->assertOk();
    $indexQueries = count(\Illuminate\Support\Facades\DB::getQueryLog());
    $indexElapsed = microtime(true) - $start;

    \Illuminate\Support\Facades\DB::flushQueryLog();
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/audit-logs/stats')->assertOk();
    $statsQueries = count(\Illuminate\Support\Facades\DB::getQueryLog());

    \Illuminate\Support\Facades\DB::flushQueryLog();
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/audit-logs/export')->assertOk();
    $exportQueries = count(\Illuminate\Support\Facades\DB::getQueryLog());

    \Illuminate\Support\Facades\DB::disableQueryLog();

    // Paginated index: count + select + eager-loaded users (`with('user:...')`)
    // — a small, bounded number of queries regardless of the 500 rows, not one
    // per row (the N+1 shape this check exists to catch).
    expect($indexQueries)->toBeLessThan(10);
    expect($statsQueries)->toBeLessThan(10);
    expect($exportQueries)->toBeLessThan(10);
    expect($indexElapsed)->toBeLessThan(2.0);
});
