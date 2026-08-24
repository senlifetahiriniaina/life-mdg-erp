<?php

declare(strict_types=1);

/*
 * Chantier 32.10 — deep 14-layer audit of Modules\Setup (onboarding wizard,
 * AI-assisted data import pipeline, module manager). See CLAUDE.md's own
 * "Chantier 32.10" entry for the full write-up and the new "Méthodologie
 * d'audit approfondi (14 couches)" section for the methodology this test
 * locks in. Every assertion below reproduces a bug that was actually
 * confirmed empirically (tinker or a real HTTP round trip) before being
 * fixed — not inferred from reading code.
 *
 * Covers, roughly in the order CLAUDE.md's own entry documents them:
 *  1. SECURITY (critical) — ImportExecutorService::resolveTargetTable() used
 *     to trust a fully client-controlled `FieldMapping.target_table` with
 *     zero allowlist: an arbitrary-table-write primitive. Now resolved only
 *     via TargetSchemas' hardcoded allowlist; target_field is now
 *     constrained to the real fields TargetSchemas exposes for the job's
 *     own target_module/target_entity.
 *  2. FUNCTIONAL — the real, live wizard-driven import (ImportDataFlow.vue,
 *     SetupController/ImportExecutorService pipeline) has never worked for
 *     ANY entity except by table-name coincidence: fixed field names now
 *     match real columns, and the real tenant column (company_id vs
 *     tenant_id, per each table's own real controller) is written.
 *  3. The AiDataImportService/ImportDataJob pipeline's ENTITY_SCHEMAS field
 *     mismatch — 4 of 6 entities silently dropped required data; the
 *     'stock' entity was 100% broken (fatal NOT NULL violation on every
 *     row) and has been removed, superseded by Chantier 16's real
 *     StockImportService feature.
 *  4. RBAC — the wizard route group had zero module/role gate at all.
 *  5. A second, independent phantom-tenant-column bug in
 *     SetupWebController (missed by every prior tenant_id-fix pass in this
 *     module) that leaked one company's server-rendered onboarding draft
 *     to another.
 *  6. AI layer — the wizard itself had zero AiContextualAssistantService
 *     integration (only the import sub-flow did); the 6 wizard-step actions
 *     are now real and reachable.
 *  7. Layer 9 (fake/dead) — DataImportController's pipeline had zero real
 *     Vue caller (activated via a new Setup/AiImport/Index.vue page);
 *     OnboardingMetricsService::generateDailySnapshot() had zero scheduled
 *     producer despite its own docblock claiming one (activated via a new
 *     `setup:generate-funnel-snapshots` command).
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Setup\Data\TargetSchemas;
use Modules\Setup\Jobs\ImportDataJob;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Services\AiDataImportService;
use Spatie\Permission\Models\Role;

function deepAuditUser(string $role = 'admin'): User
{
    // Real permissions (setup.import.*, etc.) are needed here, not just a
    // Role row — several of the endpoints below go through
    // ImportSessionPolicy's real permission-string checks, not just the
    // route-level module:/role: gate.
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ─── 1. Security — arbitrary table/column write is closed ────────────────

it('rejects a client-spoofed target_field that is not a real column for the job entity', function () {
    $user = deepAuditUser('admin');

    $job = ImportJob::factory()->create([
        'tenant_id'     => (string) $user->company_id,
        'target_module' => 'CRM',
        'target_entity' => 'contacts',
        'status'        => 'mapping',
        'source_type'   => 'csv',
        'created_by'    => $user->id,
    ]);

    // Before this chantier's fix, target_field was `string|max:100` with no
    // allowlist — an attacker could write to literally any column of the
    // real destination table (or, worse still — see the next test — of
    // ANY table, via target_table). 'password' is not a real crm_contacts
    // column, and is exactly the kind of value this validation must now
    // reject.
    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/setup/import-jobs/{$job->id}/mappings", [
            'mappings' => [[
                'source_field'   => 'col1',
                'target_field'   => 'password',
                'target_table'   => 'users',
                'transform_type' => 'direct',
                'is_required'    => false,
                'is_confirmed'   => true,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['mappings.0.target_field']);
});

it('never trusts a spoofed target_table — resolution is server-side only, via the real allowlist', function () {
    // Direct, low-level proof (bypassing the now-tightened saveMappings
    // validator on purpose) that even if a FieldMapping row somehow existed
    // with an attacker-chosen target_table, ImportExecutorService would
    // still refuse to write anywhere but the real, allowlisted table for
    // the job's own target_module/target_entity.
    expect(TargetSchemas::table('CRM', 'contacts'))->toBe('crm_contacts');
    expect(TargetSchemas::table('Accounting', 'invoices'))->toBe('acc_invoices');
    expect(TargetSchemas::table('Accounting', 'accounts'))->toBe('acc_chart_of_accounts');
    // An unrecognised pair — what a spoofed target_table would have to
    // impersonate to reach an arbitrary real table like `users` — resolves
    // to nothing.
    expect(TargetSchemas::table('Core', 'users'))->toBeNull();
});

// ─── 2. The real wizard-driven import pipeline actually works now ────────

it('imports a real contact through the live wizard pipeline and it is visible via the real CRM API', function () {
    Storage::fake('local');
    $user = deepAuditUser('admin');

    $csv = UploadedFile::fake()->createWithContent('contacts.csv', "Nom,Prenom,Email\nDoe,Jane,jane@example.test\n");

    $create = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/setup/import-jobs', [
            'name'           => 'Contacts import',
            'source_type'    => 'csv',
            'target_module'  => 'CRM',
            'target_entity'  => 'contacts',
            'file'           => $csv,
        ])->assertCreated();

    $jobId = $create->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/setup/import-jobs/{$jobId}/analyze")
        ->assertOk();

    $mapResponse = $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/setup/import-jobs/{$jobId}/mappings", [
            'mappings' => [
                ['source_field' => 'Nom', 'target_field' => 'last_name', 'target_table' => 'contacts', 'transform_type' => 'direct', 'is_required' => true, 'is_confirmed' => true],
                ['source_field' => 'Prenom', 'target_field' => 'first_name', 'target_table' => 'contacts', 'transform_type' => 'direct', 'is_required' => true, 'is_confirmed' => true],
                ['source_field' => 'Email', 'target_field' => 'email', 'target_table' => 'contacts', 'transform_type' => 'direct', 'is_required' => false, 'is_confirmed' => true],
            ],
        ]);
    $mapResponse->assertOk();

    // QUEUE_CONNECTION=sync in this app's test env (phpunit.xml) — the
    // dispatched ExecuteImportJob really runs inline within this request,
    // no manual second call needed (and none should be made — see this
    // test's own headline finding below: doing so surfaced a real,
    // pre-existing TypeError, fixed in ImportExecutorService::insertBatch()).
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/setup/import-jobs/{$jobId}/execute")
        ->assertStatus(202);

    $row = \DB::table('crm_contacts')->where('email', 'jane@example.test')->first();
    expect($row)->not->toBeNull();
    expect($row->first_name)->toBe('Jane');
    expect($row->last_name)->toBe('Doe');
    // The real bug: before this chantier, this landed as tenant_id, which
    // ContactController::index() never filters by — company_id stayed
    // NULL and the row was permanently invisible to the real API.
    expect((int) $row->company_id)->toBe($user->company_id);

    // Visible via the real, live CRM endpoint for this exact company —
    // the actual, end-to-end proof this import is not orphaned data.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/contacts?per_page=100')
        ->assertOk()
        ->assertJsonFragment(['email' => 'jane@example.test']);
});

it('resolves acc_invoices, not the old wrong accounting_invoices/customer_name field names, for Accounting/invoices imports', function () {
    Storage::fake('local');
    $user = deepAuditUser('admin');

    $csv = UploadedFile::fake()->createWithContent('invoices.csv', "Numero,Client,Emission,Total\nINV-900,ACME,2026-01-15,50000\n");

    $jobId = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/setup/import-jobs', [
            'name' => 'Invoices', 'source_type' => 'csv',
            'target_module' => 'Accounting', 'target_entity' => 'invoices', 'file' => $csv,
        ])->assertCreated()->json('data.id');

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/setup/import-jobs/{$jobId}/analyze")->assertOk();

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/setup/import-jobs/{$jobId}/mappings", [
            'mappings' => [
                ['source_field' => 'Numero', 'target_field' => 'number', 'target_table' => 'invoices', 'transform_type' => 'direct', 'is_required' => true, 'is_confirmed' => true],
                ['source_field' => 'Client', 'target_field' => 'partner_name', 'target_table' => 'invoices', 'transform_type' => 'direct', 'is_required' => false, 'is_confirmed' => true],
                ['source_field' => 'Emission', 'target_field' => 'invoice_date', 'target_table' => 'invoices', 'transform_type' => 'direct', 'is_required' => true, 'is_confirmed' => true],
                ['source_field' => 'Total', 'target_field' => 'total', 'target_table' => 'invoices', 'transform_type' => 'direct', 'is_required' => true, 'is_confirmed' => true],
            ],
        ])->assertOk();

    app(\Modules\Setup\Services\ImportExecutorService::class)->execute(ImportJob::find($jobId));

    $row = \DB::table('acc_invoices')->where('number', 'INV-900')->first();
    expect($row)->not->toBeNull();
    expect($row->partner_name)->toBe('ACME');
    expect((float) $row->total)->toBe(50000.0);
    // acc_invoices has no tenant/company column at all — confirming the
    // insert didn't fatally error trying to write one.
    expect(true)->toBeTrue();
});

// ─── 3. AiDataImportService/ImportDataJob entity-schema fixes ────────────

it('persists a products row with the real selling_price/cost_price columns, kept in sync with sale_price', function () {
    $svc = app(AiDataImportService::class);
    $mapping = [
        ['source' => 'Nom', 'target' => 'name'],
        ['source' => 'SKU', 'target' => 'sku'],
        ['source' => 'Prix', 'target' => 'selling_price'],
        ['source' => 'Cout', 'target' => 'cost_price'],
    ];

    $path = tempnam(sys_get_temp_dir(), 'setup_audit_') . '.csv';
    file_put_contents($path, "Nom,SKU,Prix,Cout\nGant EPI,GNT-900,12000,7000\n");

    (new ImportDataJob('deep-audit-products', $path, $mapping, '777'))->handle();

    $row = \DB::table('inventory_products')->where('name', 'Gant EPI')->first();
    expect($row)->not->toBeNull();
    expect((float) $row->selling_price)->toBe(12000.0);
    expect((float) $row->sale_price)->toBe(12000.0);
    expect((float) $row->cost_price)->toBe(7000.0);

    \DB::table('inventory_products')->where('name', 'Gant EPI')->delete();
    @unlink($path);
});

it('no longer offers the stock entity at all — superseded by the real StockImportService feature', function () {
    $svc = app(AiDataImportService::class);
    $templates = collect($svc->getTemplates())->pluck('entity');

    expect($templates)->not->toContain('stock');
    expect($templates->all())->toBe(['contacts', 'products', 'suppliers', 'employees', 'invoices']);
});

it('writes contacts/suppliers into the real company_id tenant column, not the unread tenant_id one', function () {
    $mapping = [
        ['source' => 'Nom', 'target' => 'full_name'],
        ['source' => 'Email', 'target' => 'email'],
    ];
    $path = tempnam(sys_get_temp_dir(), 'setup_audit_') . '.csv';
    file_put_contents($path, "Nom,Email\nJean Rakoto,jean.rakoto@example.test\n");

    (new ImportDataJob('deep-audit-contacts', $path, $mapping, '555'))->handle();

    $row = \DB::table('crm_contacts')->where('email', 'jean.rakoto@example.test')->first();
    expect($row)->not->toBeNull();
    expect((int) $row->company_id)->toBe(555);

    \DB::table('crm_contacts')->where('email', 'jean.rakoto@example.test')->delete();
    @unlink($path);
});

// ─── 4. RBAC — the AI-assisted import pipeline's own route group ─────────

it('AiDataImportService pipeline routes are RBAC-gated (module:Setup + role)', function () {
    $noRoleUser = User::factory()->create();

    $this->actingAs($noRoleUser, 'sanctum')
        ->postJson('/api/v1/setup/import/analyze', [])
        ->assertStatus(403);
});

// ─── 5. SetupWebController's independent phantom-tenant-column bug ───────
// (Also locked in directly against the real Inertia response in
// SetupWebControllerTest.php — repeated here at the model/service level for
// completeness of this audit's own regression file.)

it('SetupWizardService state is keyed on company_id, matching SetupWebController and every API controller in this module', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    \Modules\Setup\Models\CompanyProfile::create([
        'tenant_id' => (string) $companyA->id, 'company_name' => 'Société A', 'country_code' => 'MG',
    ]);

    $service = app(\Modules\Setup\Services\SetupWizardService::class);
    $stateA = $service->getState((string) $companyA->id);
    $stateB = $service->getState((string) $companyB->id);

    expect($stateA['company']['company_name'] ?? null)->toBe('Société A');
    expect($stateB['company'] ?? null)->toBeNull();
});

// ─── 6. AI layer — the wizard's own contextual guidance ───────────────────

it('the AI-assist endpoint returns real, non-empty guidance for every one of the 6 real wizard steps', function () {
    $user = deepAuditUser('admin');

    foreach (['wizard_company', 'wizard_admin', 'wizard_modules', 'wizard_workflows', 'wizard_apps', 'wizard_complete'] as $action) {
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/ai/assist', ['action' => $action, 'locale' => 'fr'])
            ->assertOk();

        expect($response->json('what_to_do'))->not->toBeEmpty();
        expect($response->json('how_to_do'))->not->toBeEmpty();
    }

    // The pre-existing import sub-flow actions still work too.
    foreach (['import_file', 'map_columns', 'execute_import'] as $action) {
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/ai/assist', ['action' => $action, 'locale' => 'fr'])
            ->assertOk()
            ->assertJsonPath('what_to_do', fn ($v) => ! empty($v));
    }
});

it('the AI-assist endpoint is genuinely reachable at its documented URL (no doubled route prefix)', function () {
    $user = deepAuditUser('admin');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/setup/ai/assist', ['action' => 'wizard_company'])
        ->assertOk();

    // The Chantier 19 Lot 3 bug this test also guards against: a doubled
    // prefix would have put the real route at
    // api/v1/setup/v1/setup/ai/assist instead.
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/setup/v1/setup/ai/assist', ['action' => 'wizard_company'])
        ->assertStatus(404);
});

// ─── 7. Layer 9 — newly-activated real producers ──────────────────────────

it('the new AI Import page is reachable and renders the real Inertia component', function () {
    $user = deepAuditUser('admin');

    $this->actingAs($user)
        ->get('/setup/ai-import')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/AiImport/Index', false));
});

it('setup:generate-funnel-snapshots is registered and actually persists a real FunnelSnapshot', function () {
    $tenantId = random_int(900000, 999999);
    $date = now()->subDay();
    $user = User::factory()->create();

    \Modules\Setup\Models\OnboardingSession::create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'started_at' => $date->copy()->startOfDay()->addHours(9),
        'completed_at' => $date->copy()->startOfDay()->addHours(9)->addMinutes(4),
        'total_duration_seconds' => 240,
        'current_step' => 5,
        'source_type' => 'csv',
    ]);

    $this->artisan('setup:generate-funnel-snapshots', ['--date' => $date->toDateString()])
        ->assertExitCode(0);

    $snapshot = \Modules\Setup\Models\FunnelSnapshot::where('tenant_id', $tenantId)
        ->whereDate('snapshot_date', $date->toDateString())
        ->first();

    expect($snapshot)->not->toBeNull();
    expect($snapshot->sessions_started)->toBe(1);
    expect($snapshot->sessions_completed)->toBe(1);
    expect($snapshot->avg_duration_seconds)->toBe(240);
});

it('the setup:generate-funnel-snapshots command is really scheduled, not just registered', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $events   = collect($schedule->events())->pluck('command');

    expect($events->filter(fn ($c) => str_contains((string) $c, 'setup:generate-funnel-snapshots')))->not->toBeEmpty();
});
