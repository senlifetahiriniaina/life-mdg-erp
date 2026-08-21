<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Services\Forecasting\CashflowForecastService;
use Modules\Analytics\Services\ForecastingEngineService;

uses(RefreshDatabase::class);

/**
 * Chantier 26 (volet A) — prévisions de trésorerie (encaissement/décaissement)
 * bâties sur l'historique réel des 6 derniers mois (au moins) via le grand
 * livre OHADA réel (acc_journal_entry_lines/acc_journal_entries/acc_chart_of_
 * accounts, classe 5). Verrouille 2 bugs sévères trouvés par exécution réelle
 * (tinker), pas par relecture de code :
 *   1. ForecastingEngineService::collect*Data() groupait/triait par l'alias
 *      SELECT "date" — SQLite traite un identifiant entre guillemets non
 *      résolu comme un littéral, donc GROUP BY "date" collabait TOUTES les
 *      lignes dans un seul groupe.
 *   2. ForecastPrediction/ForecastModel utilisaient la mauvaise clé FK
 *      (model_id/base_model_id au lieu de la vraie colonne NOT NULL
 *      forecast_model_id) — violation de contrainte garantie dès qu'une
 *      vraie prédiction devait être persistée.
 */
function chantier26User(): \App\Models\User
{
    // The chart of accounts / journals (BNQ, 512, 707) are only seeded by
    // AccountingDatabaseSeeder, not by RolesAndPermissionsSeeder —
    // actingAsUser() only auto-seeds the latter (same precedent already
    // documented for CostingSheetTest/Chantier22DepositBalanceTest).
    if (ChartOfAccount::count() === 0) {
        test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    }

    $user = actingAsUser('admin');
    $user->forceFill(['company_id' => Company::create([
        'name'     => 'Chantier26 Co '.uniqid(),
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ])->id])->save();

    return $user;
}

function postTreasuryEntry(int $daysAgo, float $debit512, float $credit512, string $ref): JournalEntry
{
    $bank    = ChartOfAccount::where('code', '512')->firstOrFail();
    $sales   = ChartOfAccount::where('code', '707')->firstOrFail();
    $journal = Journal::where('code', 'BNQ')->first() ?? Journal::firstOrFail();

    $entry = JournalEntry::create([
        'journal_id'  => $journal->id,
        'entry_date'  => now()->subDays($daysAgo),
        'reference'   => $ref,
        'description' => 'Chantier26 test',
    ]);

    JournalEntryLine::create([
        'entry_id'   => $entry->id,
        'account_id' => $bank->id,
        'debit'      => $debit512,
        'credit'     => $credit512,
    ]);
    JournalEntryLine::create([
        'entry_id'   => $entry->id,
        'account_id' => $sales->id,
        'debit'      => $credit512,
        'credit'     => $debit512,
    ]);

    return $entry;
}

describe('CashflowForecastService — real ledger data', function () {
    beforeEach(function () {
        $this->user = chantier26User();
    });

    test('current treasury balance reflects real posted journal entries, not a hardcoded 0', function () {
        postTreasuryEntry(30, 500000, 0, 'C26-1');
        postTreasuryEntry(10, 0, 150000, 'C26-2');

        $svc    = app(CashflowForecastService::class);
        $result = $svc->forecast90Days($this->user->company_id, 30);

        // 500,000 debit - 150,000 credit = 350,000 opening balance, then the
        // real average daily inflow/outflow (derived from the same 2 entries
        // over the 180-day lookback) compounds forward for 30 days.
        expect($result['daily'][0]['running_balance'])->toBeGreaterThan(350000)
            ->and($result['summary']['final_balance'])->toBeGreaterThan($result['daily'][0]['running_balance']);
    });

    test('respects the requested horizon instead of always defaulting to 90', function () {
        $svc = app(CashflowForecastService::class);

        expect(count($svc->forecast90Days($this->user->company_id, 30)['daily']))->toBe(30)
            ->and($svc->forecast90Days($this->user->company_id, 30)['horizon_days'])->toBe(30)
            ->and(count($svc->forecast90Days($this->user->company_id, 180)['daily']))->toBe(180);
    });

    test('OHADA classe 5 projection lists every treasury account, including zero-movement ones', function () {
        $svc    = app(CashflowForecastService::class);
        $result = $svc->getOhadaProjection($this->user->company_id);

        expect($result)->toHaveKey('classe5')
            ->and(collect($result['classe5'])->pluck('account_code'))->toContain('512', '530');
    });
});

describe('API — GET /api/v1/forecasting/cashflow', function () {
    beforeEach(function () {
        $this->user = chantier26User();
    });

    test('honors ?days= within the allowlist', function () {
        $response = $this->getJson('/api/v1/forecasting/cashflow?days=180');
        $response->assertStatus(200);
        expect($response->json('horizon_days'))->toBe(180)
            ->and(count($response->json('daily')))->toBe(180);
    });

    test('falls back to 90 for an out-of-range days value', function () {
        $response = $this->getJson('/api/v1/forecasting/cashflow?days=999');
        $response->assertStatus(200);
        expect($response->json('horizon_days'))->toBe(90);
    });

    test('a role outside the allowed list is denied', function () {
        actingAsUser('sales-rep');
        $this->getJson('/api/v1/forecasting/cashflow')->assertStatus(403);
    });
});

describe('API — export endpoints', function () {
    beforeEach(function () {
        $this->user = chantier26User();
        postTreasuryEntry(15, 300000, 50000, 'C26-EXPORT');
    });

    test('GET cashflow/export/pdf streams a real PDF download', function () {
        $response = $this->get('/api/v1/forecasting/cashflow/export/pdf?days=30');
        $response->assertStatus(200);
        expect($response->headers->get('content-type'))->toContain('application/pdf');
    });

    test('GET cashflow/export/excel streams a real xlsx download', function () {
        $response = $this->get('/api/v1/forecasting/cashflow/export/excel?days=30');
        $response->assertStatus(200);
        expect($response->headers->get('content-type'))->toContain('spreadsheetml');
    });
});

describe('Web — /analytics/cashflow-forecast', function () {
    test('renders the real Inertia page for an authorized role', function () {
        chantier26User();

        $response = $this->get('/analytics/cashflow-forecast');
        $response->assertStatus(200);
        // Second arg `false` disables Inertia's PHP-side page-existence check —
        // this app's custom resolve() in resources/js/app.js strips the leading
        // module segment against Modules/<Module>/resources/js/Pages/**, a
        // convention the default finder doesn't understand (established
        // precedent: AnalyticsScreensWebTest.php's own 'Analytics/Index' assertion).
        $response->assertInertia(fn ($page) => $page->component('Analytics/CashflowForecast/Index', false));
    });
});

describe('ForecastingEngineService — GROUP BY alias-collapse fix (regression)', function () {
    beforeEach(function () {
        $this->user = chantier26User();
    });

    test('collectCashflowData does not collapse rows from different dates into one bucket', function () {
        postTreasuryEntry(60, 200000, 0, 'C26-COLLECT-1');
        postTreasuryEntry(5, 100000, 0, 'C26-COLLECT-2');

        $svc  = app(ForecastingEngineService::class);
        $data = (new ReflectionClass($svc))->getMethod('collectCashflowData');
        $data->setAccessible(true);
        $rows = $data->invoke($svc, $this->user->company_id);

        expect(count($rows))->toBeGreaterThanOrEqual(2);
    });

    test('training a real forecast model persists predictions without a FK constraint violation', function () {
        postTreasuryEntry(90, 200000, 0, 'C26-TRAIN-1');
        postTreasuryEntry(60, 150000, 0, 'C26-TRAIN-2');
        postTreasuryEntry(30, 180000, 0, 'C26-TRAIN-3');
        postTreasuryEntry(5, 120000, 0, 'C26-TRAIN-4');

        $model = ForecastModel::create([
            'tenant_id'    => $this->user->company_id,
            'name'         => 'Chantier26 cashflow model',
            'module'       => 'cashflow',
            'entity_type'  => 'global',
            'entity_id'    => 0,
            'algorithm'    => 'linear_regression',
            'horizon_days' => 30,
            'is_active'    => true,
        ]);

        $svc = app(ForecastingEngineService::class);
        $svc->train($model);

        $model->refresh();
        expect($model->predictions()->count())->toBeGreaterThan(0)
            ->and($model->predictions()->first()->forecast_model_id)->toBe($model->id);
    });
});
