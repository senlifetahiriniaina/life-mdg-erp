<?php

declare(strict_types=1);

/*
 * Chantier 19 Lot 3 — Setup re-verification (empirical execution, not
 * code-reading only). See CLAUDE.md's own "Chantier 19 Lot 3" entry for
 * the full write-up. Covers:
 *
 *  1. SetupWizardController::tenantId() — was `$user->tenant_id ?? 'default'`
 *     (the phantom column, never fixed by Chantier 8.5sv/10's earlier
 *     SetupController/OnboardingMetricsController/AdminCompanyController/
 *     AdminModulesController passes, which all missed this file) — every
 *     company's onboarding wizard draft state silently collapsed into one
 *     shared bucket.
 *  2. DataImportController::resolveTenantId() — was a fully client-
 *     controlled `tenant_id` request field, falling back to the phantom
 *     `users.tenant_id` column and then a shared `'default'` bucket.
 *  3. SetupAiAssistController::assist() — was reading the phantom
 *     `users.role` column instead of the real Spatie role.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

function setupReauditUser(string $role = 'admin'): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

it('isolates the onboarding wizard state between two real companies', function () {
    $userA = setupReauditUser('admin');
    $userB = setupReauditUser('admin');

    $this->actingAs($userA, 'sanctum')
        ->postJson('/api/v1/setup/wizard/company', [
            'company_name' => 'Société A',
            'country_code' => 'MG',
        ])->assertOk();

    // Company B has never touched the wizard — its state must be empty,
    // not Company A's, even though both are on step "company" in
    // isolation. Before the fix, both companies' users.tenant_id resolved
    // to the same phantom 'default' bucket, so B would have seen A's data.
    $stateB = $this->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/setup/wizard/state')
        ->assertOk()
        ->json('data');

    expect($stateB['company'])->toBeNull();

    $stateA = $this->actingAs($userA, 'sanctum')
        ->getJson('/api/v1/setup/wizard/state')
        ->assertOk()
        ->json('data');

    expect($stateA['company']['company_name'] ?? null)->toBe('Société A');

    // Company B saves its own, different company name — must not clobber A's.
    $this->actingAs($userB, 'sanctum')
        ->postJson('/api/v1/setup/wizard/company', [
            'company_name' => 'Société B',
            'country_code' => 'SN',
        ])->assertOk();

    $stateAAfter = $this->actingAs($userA, 'sanctum')
        ->getJson('/api/v1/setup/wizard/state')
        ->assertOk()
        ->json('data');

    expect($stateAAfter['company']['company_name'])->toBe('Société A');
});

it('derives the AI-assisted import tenant from the real company, ignoring a client-supplied tenant_id', function () {
    $userA = setupReauditUser('employee');
    $userB = setupReauditUser('employee');

    $file = UploadedFile::fake()->createWithContent(
        'contacts.csv',
        "nom,email\nJean Rakoto,jean@example.com\n",
    );

    // Company A analyzes a file while trying to spoof tenant_id=999 in the
    // request body — before the fix, that client-supplied value was
    // trusted outright.
    $responseA = $this->actingAs($userA, 'sanctum')
        ->postJson('/api/v1/setup/import/analyze', [
            'file'      => $file,
            'tenant_id' => '999',
        ])->assertOk();

    $filePathA = $responseA->json('file_path');

    // The stored path is namespaced "imports/{tenantId}" — must reflect
    // Company A's own real company_id, never the spoofed 999 nor the
    // fallback 'default' bucket both companies would have shared before.
    expect($filePathA)->toContain('imports/' . $userA->company_id);
    expect($filePathA)->not->toContain('imports/999');
    expect($filePathA)->not->toContain('imports/default');

    // Company B analyzing its own file must land in ITS OWN company_id
    // bucket, not the same one Company A landed in.
    $file2 = UploadedFile::fake()->createWithContent(
        'contacts2.csv',
        "nom,email\nMarie Rasoa,marie@example.com\n",
    );
    $responseB = $this->actingAs($userB, 'sanctum')
        ->postJson('/api/v1/setup/import/analyze', ['file' => $file2])
        ->assertOk();

    expect($responseB->json('file_path'))->toContain('imports/' . $userB->company_id);
    expect($userA->company_id)->not->toBe($userB->company_id);
});

it('runs the Setup AI-assist endpoint without a fatal error using the real Spatie role', function () {
    $user = setupReauditUser('admin');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/setup/ai/assist', [
            'action' => 'import_file',
            'locale' => 'fr',
        ])
        ->assertOk()
        ->assertJsonStructure(['enabled', 'what_to_do', 'how_to_do']);
});
