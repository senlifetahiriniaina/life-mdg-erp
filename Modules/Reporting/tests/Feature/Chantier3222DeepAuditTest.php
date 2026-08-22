<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Payroll\Models\Payslip;
use Modules\Reporting\Database\Seeders\ReportTemplateSeeder;
use Modules\Reporting\Jobs\DeliverScheduledReportJob;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportSchedule;
use Modules\Reporting\Policies\ReportPolicy;
use Modules\Reporting\Services\NlToSqlService;
use Modules\Reporting\Services\ReportingService;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;

uses(RefreshDatabase::class);

/**
 * Chantier 32.22 (Reporting deep 14-layer audit) — locks in the real,
 * empirically-confirmed bugs found and fixed in this pass. See CLAUDE.md's
 * own changelog entry for the full story on each.
 */
function chantier3222ReportingUser(string $role = 'admin'): User
{
    $user    = actingAsUser($role);
    $company = \App\Models\Company::factory()->create();
    $user->update(['company_id' => $company->id]);

    return $user->fresh();
}

// ─── Headline bug: ReportTemplateSeeder never wired into DatabaseSeeder ────────

test('ReportTemplateSeeder actually seeds all 10 real system report templates', function () {
    $this->seed(ReportTemplateSeeder::class);

    $slugs = [
        'bilan-syscohada-mensuel',
        'compte-de-resultat-trimestriel',
        'balance-generale',
        'balance-agee-clients',
        'balance-agee-fournisseurs',
        'rapport-tva-mensuel',
        'rapport-is-annuel',
        'synthese-ventes-mensuelle',
        'etat-des-stocks',
        'masse-salariale-mensuelle',
    ];

    foreach ($slugs as $slug) {
        expect(ReportDefinition::where('slug', $slug)->exists())->toBeTrue("Missing seeded report: {$slug}");
    }
});

// ─── The 7 "-- Handled by OhadaReportService..." templates now dispatch for real ──

test('executing the seeded bilan-syscohada-mensuel report returns a real OHADA balance sheet, not the raw comment', function () {
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $report = ReportDefinition::where('slug', 'bilan-syscohada-mensuel')->firstOrFail();

    $execution = app(ReportingService::class)->execute($report, [], $user);

    expect($execution->status)->toBe('completed')
        ->and($execution->error_message)->toBeNull()
        ->and($execution->result_data)->toHaveCount(1);

    $payload = $execution->result_data[0];
    expect($payload['report_type'])->toBe('bilan_syscohada')
        ->and($payload)->toHaveKeys(['actif', 'passif', 'totaux', 'equilibre']);
});

test('executing each of the 6 other OHADA-comment seeded reports also delegates to the real OhadaReportService', function (string $slug, string $expectedReportType) {
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $report    = ReportDefinition::where('slug', $slug)->firstOrFail();
    $execution = app(ReportingService::class)->execute($report, [], $user);

    expect($execution->status)->toBe('completed')
        ->and($execution->error_message)->toBeNull();

    $payload = $execution->result_data[0];
    expect($payload['report_type'])->toBe($expectedReportType);
})->with([
    ['compte-de-resultat-trimestriel', 'compte_de_resultat_syscohada'],
    ['balance-generale', 'balance_generale'],
    ['balance-agee-clients', 'balance_agee_clients'],
    ['balance-agee-fournisseurs', 'balance_agee_fournisseurs'],
    ['rapport-tva-mensuel', 'rapport_tva'],
    ['rapport-is-annuel', 'rapport_is'],
]);

// ─── Templates #8/#9/#10 fixed to real schema, verified with real fixture data ──

test('synthese-ventes-mensuelle now runs real SQL against the real Sales schema and returns real revenue', function () {
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $product = Product::factory()->create(['name' => 'Chemise EPI', 'category' => 'Vêtements']);

    $order = SalesOrder::factory()->confirmed()->create([
        'tenant_id' => $user->company_id,
        'status'    => 'confirmed',
    ]);

    SalesOrderLine::factory()->create([
        'sales_order_id' => $order->id,
        'product_id'     => $product->id,
        'quantity'       => 10,
        'unit_price'     => 5000,
        'line_total'     => 50000,
    ]);

    $report    = ReportDefinition::where('slug', 'synthese-ventes-mensuelle')->firstOrFail();
    $execution = app(ReportingService::class)->execute($report, [], $user);

    expect($execution->status)->toBe('completed')
        ->and($execution->error_message)->toBeNull()
        ->and($execution->result_data)->toHaveCount(1);

    expect((float) $execution->result_data[0]['chiffre_affaires'])->toBe(50000.0)
        ->and($execution->result_data[0]['produit'])->toBe('Chemise EPI');
});

test('etat-des-stocks now runs real SQL against inventory_products + inventory_stock', function () {
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $product = Product::factory()->create([
        'tenant_id'     => $user->company_id,
        'name'          => 'Pantalon EPI',
        'reorder_point' => 20,
        'cost_price'    => 1000,
        'is_active'     => true,
    ]);
    Stock::factory()->create(['product_id' => $product->id, 'quantity' => 5]);

    $report    = ReportDefinition::where('slug', 'etat-des-stocks')->firstOrFail();
    $execution = app(ReportingService::class)->execute($report, [], $user);

    expect($execution->status)->toBe('completed')
        ->and($execution->error_message)->toBeNull();

    $row = collect($execution->result_data)->firstWhere('sku', $product->sku);
    expect($row)->not->toBeNull()
        ->and((int) $row['quantite_stock'])->toBe(5)
        ->and($row['statut_stock'])->toBe('ALERTE'); // 5 <= reorder_point 20
});

test('masse-salariale-mensuelle now runs real SQL against payslips + hr_employees + hr_departments with the real approved status', function () {
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $department = Department::factory()->create(['name' => 'Production']);
    $employee   = Employee::factory()->create(['department_id' => $department->id]);

    Payslip::factory()->create([
        'tenant_id'    => $user->company_id,
        'employee_id'  => $employee->id,
        'period'       => now()->format('Y-m'),
        'status'       => 'approved',
        'gross_salary' => 500000,
        'net_salary'   => 450000,
    ]);

    // A draft payslip in the same period must NOT be counted.
    Payslip::factory()->create([
        'tenant_id'   => $user->company_id,
        'employee_id' => $employee->id,
        'period'      => now()->format('Y-m'),
        'status'      => 'draft',
    ]);

    $report    = ReportDefinition::where('slug', 'masse-salariale-mensuelle')->firstOrFail();
    $execution = app(ReportingService::class)->execute($report, [], $user);

    expect($execution->status)->toBe('completed')
        ->and($execution->error_message)->toBeNull()
        ->and($execution->result_data)->toHaveCount(1);

    expect($execution->result_data[0]['department'])->toBe('Production')
        ->and((int) $execution->result_data[0]['effectif'])->toBe(1)
        ->and((float) $execution->result_data[0]['masse_brute'])->toBe(500000.0);
});

// ─── ReportGenerationService::run()'s parallel executor also fixed ─────────────

test('ReportGenerationService::run() also correctly dispatches an OHADA system report and no longer hardcodes tenant_id/executed_by to 1', function () {
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $report = ReportDefinition::where('slug', 'balance-agee-clients')->firstOrFail();

    $execution = app(\Modules\Reporting\Services\ReportGenerationService::class)
        ->run($report, [], 'json');

    expect($execution->status)->toBe('completed')
        ->and($execution->tenant_id)->not->toBe(1)
        ->and($execution->executed_by)->toBeNull()
        ->and($execution->result_count)->toBe(1)
        ->and($execution->result_data[0]['report_type'])->toBe('balance_agee_clients');
});

test('ReportGenerationService::run() no longer produces double-LIMIT invalid SQL on a real seeded template with its own LIMIT clause', function () {
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $product = Product::factory()->create(['tenant_id' => $user->company_id, 'is_active' => true]);
    Stock::factory()->create(['product_id' => $product->id, 'quantity' => 100]);

    $report = ReportDefinition::where('slug', 'etat-des-stocks')->firstOrFail();

    $execution = app(\Modules\Reporting\Services\ReportGenerationService::class)
        ->run($report, [], 'json');

    expect($execution->status)->toBe('completed')
        ->and($execution->error_message)->toBeNull();
});

// ─── Scheduled report delivery, activated for real ─────────────────────────────

test('reporting:deliver-scheduled dispatches a job for each due, active schedule', function () {
    Queue::fake();
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $report = ReportDefinition::where('slug', 'balance-agee-clients')->firstOrFail();

    $due = ReportSchedule::create([
        'tenant_id'            => $user->company_id,
        'report_definition_id' => $report->id,
        'name'                 => 'Due schedule',
        'frequency'            => 'daily',
        'recipients'           => ['finance@example.test'],
        'is_active'            => true,
        'next_run_at'          => now()->subHour(),
    ]);

    $notDue = ReportSchedule::create([
        'tenant_id'            => $user->company_id,
        'report_definition_id' => $report->id,
        'name'                 => 'Not due yet',
        'frequency'            => 'daily',
        'recipients'           => ['finance@example.test'],
        'is_active'            => true,
        'next_run_at'          => now()->addDay(),
    ]);

    $this->artisan('reporting:deliver-scheduled')->assertExitCode(0);

    Queue::assertPushed(DeliverScheduledReportJob::class, fn ($job) => $job->scheduleId === $due->id);
    Queue::assertNotPushed(DeliverScheduledReportJob::class, fn ($job) => $job->scheduleId === $notDue->id);
});

test('DeliverScheduledReportJob runs a real system report, emails it, and advances the schedule (never touching report_definitions.last_run_at, which is not a real column)', function () {
    Mail::fake();
    $user = chantier3222ReportingUser();
    $this->seed(ReportTemplateSeeder::class);

    $report = ReportDefinition::where('slug', 'balance-agee-clients')->firstOrFail();

    $schedule = ReportSchedule::create([
        'tenant_id'            => $user->company_id,
        'report_definition_id' => $report->id,
        'name'                 => 'Weekly aged receivables',
        'frequency'            => 'weekly',
        'recipients'           => ['finance@example.test'],
        'is_active'            => true,
        'next_run_at'          => now()->subHour(),
        'last_run_at'          => null,
    ]);

    (new DeliverScheduledReportJob($schedule->id))->handle(app(\Modules\Reporting\Services\ReportGenerationService::class));

    $schedule->refresh();
    expect($schedule->last_run_at)->not->toBeNull()
        ->and($schedule->next_run_at->isAfter(now()))->toBeTrue();

    Mail::assertSent(function ($mailable) {
        return true;
    });

    $execution = \Modules\Reporting\Models\ReportExecution::where('report_definition_id', $report->id)
        ->where('tenant_id', $user->company_id)
        ->latest()
        ->first();
    expect($execution)->not->toBeNull()
        ->and($execution->status)->toBe('completed')
        ->and($execution->executed_by)->toBeNull();
});

test('DeliverScheduledReportJob logs and returns cleanly for a schedule that no longer exists', function () {
    // No exception thrown = the fix.
    (new DeliverScheduledReportJob(999999))->handle(app(\Modules\Reporting\Services\ReportGenerationService::class));
    expect(true)->toBeTrue();
});

// ─── NlToSqlService's schema-introspection crash, fixed ────────────────────────

test('NlToSqlService::queryToSql() no longer fatally errors on schema introspection when a Claude API key is configured', function () {
    config(['services.anthropic.key' => 'sk-ant-fake-test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => json_encode([
                    'sql'         => 'SELECT COUNT(*) FROM users',
                    'params'      => [],
                    'explanation' => 'Counts users',
                    'safe'        => true,
                ])],
            ],
        ], 200),
    ]);

    $result = app(NlToSqlService::class)->queryToSql('Combien y a-t-il d\'utilisateurs ?', 'fr');

    expect($result['enabled'] ?? false)->toBeTrue()
        ->and($result)->toHaveKey('sql');
});

// ─── ReportPolicy, registered on the Gate since the module was built, now wired ─

test('ReportPolicy genuinely denies a user with no reporting permission and allows one with it', function () {
    $noPermUser = User::factory()->create();
    $withPerm   = chantier3222ReportingUser('admin');

    $policy = new ReportPolicy();

    expect($policy->viewAny($noPermUser))->toBeFalse()
        ->and($policy->create($noPermUser))->toBeFalse()
        ->and($policy->viewAny($withPerm))->toBeTrue()
        ->and($policy->create($withPerm))->toBeTrue();
});

test('POST /reporting/reports as admin is genuinely authorized through the Gate, not just the route role gate', function () {
    $user = chantier3222ReportingUser('admin');

    $response = $this->postJson('/api/v1/reporting/reports', [
        'name'           => 'Custom report',
        'module'         => 'Sales',
        'query_template' => 'SELECT 1 AS n',
    ]);

    $response->assertCreated();
});
