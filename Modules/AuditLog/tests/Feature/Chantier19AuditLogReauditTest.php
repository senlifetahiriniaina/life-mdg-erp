<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Chantier 19 Lot 3: empirical re-verification of Chantier 8.5-light's
 * AuditLog cross-tenant leak fix (core_audit_logs.company_id, backfilled +
 * populated by every real writer — RecordsActivity, AuditableActions,
 * AuditAuthListener). The existing test suite asserted the writers'
 * *shape* (factory-built rows with company_id set directly) but never
 * exercised a real writer end-to-end for two different companies and then
 * read them back over the real GET /api/v1/audit-logs route — exactly the
 * gap this session's empirical-execution methodology targets. Re-verified
 * here rather than trusted from the changelog.
 */
function auditLogReauditUser(string $companySuffix): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $company = Company::create([
        'name' => "Chantier19 AuditLog Co {$companySuffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->assignRole('admin');

    DB::table('tenant_modules')->updateOrInsert(
        ['tenant_id' => (string) $user->id, 'module' => 'AuditLog', 'department' => null],
        ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
    );

    return $user;
}

test('a real login event for company A is invisible to company B over the real audit-logs route', function () {
    $userA = auditLogReauditUser('A');
    $userB = auditLogReauditUser('B');

    // Fires the real AuditAuthListener path (the actual writer, not a
    // factory-built row) for a company-A user.
    event(new Login('sanctum', $userA, false));

    $listAsA = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/audit-logs?event_type=login');
    $listAsA->assertOk();
    expect(collect($listAsA->json('data'))->pluck('user_id'))->toContain($userA->id);

    $listAsB = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/audit-logs?event_type=login');
    $listAsB->assertOk();
    expect(collect($listAsB->json('data'))->pluck('user_id'))->not->toContain($userA->id);

    // Direct-by-id lookup also 404s for company B, not just excluded from the list.
    $entryId = \Modules\Core\Models\AuditLog::where('user_id', $userA->id)->where('event_type', 'login')->latest()->first()->id;
    $showAsB = $this->actingAs($userB, 'sanctum')->getJson("/api/v1/audit-logs/{$entryId}");
    $showAsB->assertNotFound();

    $statsAsB = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/audit-logs/stats');
    $statsAsB->assertOk();
    expect($statsAsB->json('today'))->toBe(0);
});

test('AuditableActions-driven writes populate the real tenant boundary column, not left null', function () {
    $userA = auditLogReauditUser('C');

    app(\Illuminate\Support\Facades\Auth::class);
    $this->actingAs($userA, 'sanctum');

    // Directly exercise the same trait logic AuditableActions uses (both
    // its call sites resolve auth()->user()?->company_id ?? 0 — confirmed
    // via source read above) against a real authenticated actor to lock in
    // that the column is genuinely populated end-to-end, not just present
    // in the trait's source.
    $service = app(\Modules\Core\Services\AuditService::class);
    $log = $service->log(
        action: 'export',
        userId: $userA->id,
        module: 'AuditLog',
        eventType: 'export',
        description: 'Chantier 19 Lot 3 regression probe',
    );

    expect((int) $log->company_id)->toBe((int) $userA->company_id);
});
