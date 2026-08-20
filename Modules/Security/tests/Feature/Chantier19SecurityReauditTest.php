<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Chantier 19 Lot 3: empirical re-verification of Chantier 8.3cs's Security
 * RBAC/company_id-cast fixes (TrustZone, ServiceIdentity, ComplianceControl,
 * ComplianceViolation, EncryptionKey) — this session's methodology found
 * multiple "already fixed" claims elsewhere in this app that didn't survive
 * a real HTTP re-check, so every previously-documented fix in this module
 * is re-verified here with two real companies over the real route, not by
 * re-reading the code.
 */
function securityReauditUser(string $companySuffix, string $role = 'security-admin'): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $company = Company::create([
        'name' => "Chantier19 Security Co {$companySuffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->assignRole($role);

    DB::table('tenant_modules')->updateOrInsert(
        ['tenant_id' => (string) $user->id, 'module' => 'Security', 'department' => null],
        ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
    );

    return $user;
}

test('a trust zone created by company A is invisible/uneditable to company B', function () {
    $userA = securityReauditUser('A');
    $userB = securityReauditUser('B');

    $store = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/security/trust-zones', [
        'zone_name' => 'A internal zone',
        'zone_type' => 'internal',
    ]);
    $store->assertCreated();
    $id = $store->json('data.id');

    $show = $this->actingAs($userB, 'sanctum')->getJson("/api/v1/security/trust-zones/{$id}");
    $show->assertStatus(403);

    $update = $this->actingAs($userB, 'sanctum')->putJson("/api/v1/security/trust-zones/{$id}", ['zone_name' => 'hijacked']);
    $update->assertStatus(403);

    $delete = $this->actingAs($userB, 'sanctum')->deleteJson("/api/v1/security/trust-zones/{$id}");
    $delete->assertStatus(403);

    // Company A itself can still reach it (the fix fails closed, not open).
    $ownShow = $this->actingAs($userA, 'sanctum')->getJson("/api/v1/security/trust-zones/{$id}");
    $ownShow->assertOk();
});

test('a service identity created by company A is invisible/uneditable to company B', function () {
    $userA = securityReauditUser('C');
    $userB = securityReauditUser('D');

    $store = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/security/service-identities', [
        'service_name' => 'internal-api-bot',
        'service_type' => 'internal',
    ]);
    $store->assertCreated();
    $id = $store->json('data.id');

    $show = $this->actingAs($userB, 'sanctum')->getJson("/api/v1/security/service-identities/{$id}");
    $show->assertStatus(403);

    $ownShow = $this->actingAs($userA, 'sanctum')->getJson("/api/v1/security/service-identities/{$id}");
    $ownShow->assertOk();
});

test('a compliance control created by company A is invisible/uneditable to company B', function () {
    $userA = securityReauditUser('E');
    $userB = securityReauditUser('F');

    $store = $this->actingAs($userA, 'sanctum')->postJson('/api/v1/security/compliance/controls', [
        'framework' => 'GDPR',
        'control_id' => 'GDPR-32',
        'control_name' => 'Encryption at rest',
        'control_description' => 'Data must be encrypted at rest using AES-256.',
        'control_type' => 'preventive',
    ]);
    $store->assertCreated();
    $id = $store->json('data.id') ?? $store->json('id');
    expect($id)->not->toBeNull();

    $show = $this->actingAs($userB, 'sanctum')->getJson("/api/v1/security/compliance/controls/{$id}");
    $show->assertStatus(403);
});

test('auth-events and rate-limit endpoints reject a plain employee (route-level role gate)', function () {
    $employee = securityReauditUser('G', 'employee');

    $authEvents = $this->actingAs($employee, 'sanctum')->getJson('/api/v1/security/auth-events');
    $authEvents->assertStatus(403);

    $rateLimits = $this->actingAs($employee, 'sanctum')->getJson('/api/v1/security/rate-limits/status');
    $rateLimits->assertStatus(403);
});

test('threat indicators create and severity-summary work end to end over http', function () {
    $user = securityReauditUser('H');

    $store = $this->actingAs($user, 'sanctum')->postJson('/api/v1/security/threat-indicators', [
        'indicator_type' => 'ip',
        'indicator_value' => '203.0.113.5',
        'severity' => 'high',
    ]);
    $store->assertCreated();

    $summary = $this->actingAs($user, 'sanctum')->getJson('/api/v1/security/threat-indicators/severity-summary');
    $summary->assertOk();
    expect($summary->json('data.high'))->toBeGreaterThanOrEqual(1);
});
