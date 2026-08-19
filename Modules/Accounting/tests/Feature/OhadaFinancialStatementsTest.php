<?php

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;

/**
 * Chantier 18 — Madagascar/OHADA-standard financial statements.
 *
 * Confirms the real bugs found and fixed this chantier stay fixed:
 *  - OhadaReportService::getAccountBalances()/getPeriodMovements() used to
 *    query `accounting_journal_lines`/`accounting_journal_entries`, a table
 *    pair with NO migration anywhere in the repo — every call silently
 *    degraded to empty data via the surrounding try/catch. Repointed at the
 *    real, live `acc_journal_entry_lines`/`acc_journal_entries` ledger.
 *  - extractAccounts()/sumAccounts()/sumMovements() fataled the moment real
 *    (non-empty) data reached them: PHP auto-casts purely-numeric string
 *    array keys to int, breaking str_starts_with().
 *  - The Bilan never folded the period's net result (classes 6/7) into
 *    capitaux propres, so it could never balance except with zero
 *    transactions — fixed to compute it live.
 */
beforeEach(function () {
    $this->user = actingAsUser('accountant');

    $this->accountClients = ChartOfAccount::factory()->create(['code' => '411', 'name' => 'Clients', 'type' => 'asset']);
    $this->accountVentes = ChartOfAccount::factory()->create(['code' => '707', 'name' => 'Ventes', 'type' => 'revenue']);
});

test('ohadaBalanceSheet delegates to the real OhadaReportService and returns a balanced, rubrique-structured bilan', function () {
    $entry = JournalEntry::create([
        'entry_number' => 'TEST-1',
        'date' => now()->toDateString(),
        'description' => 'Vente test',
        'status' => 'posted',
        'currency' => 'MGA',
    ]);
    $entry->lines()->create(['account_id' => $this->accountClients->id, 'debit' => 10000, 'credit' => 0]);
    $entry->lines()->create(['account_id' => $this->accountVentes->id, 'debit' => 0, 'credit' => 10000]);

    $response = $this->postJson('/api/v1/accounting/financial-reports/ohada/balance-sheet', [
        'period' => now()->format('Y-m'),
    ]);

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data['report_type'])->toBe('bilan_syscohada');
    expect($data['currency'])->toBe('MGA');
    expect($data['actif'])->toHaveKeys(['immobilisations', 'stocks', 'creances', 'tresorerie_actif']);
    expect($data['passif'])->toHaveKeys(['capitaux_propres', 'dettes_financieres', 'dettes_circulantes', 'tresorerie_passif']);
    expect($data['equilibre'])->toBeTrue();
    expect((float) $data['totaux']['total_actif'])->toEqualWithDelta((float) $data['totaux']['total_passif'], 0.01);
    // 411 (Clients, classe 4 débit) should show up as a real créance line.
    expect((float) $data['actif']['creances']['total'])->toBe(10000.0);
});

test('ohadaIncomeStatement delegates to the real OhadaReportService and computes the SYSCOHADA cascade', function () {
    $entry = JournalEntry::create([
        'entry_number' => 'TEST-2',
        'date' => now()->toDateString(),
        'description' => 'Vente test',
        'status' => 'posted',
        'currency' => 'MGA',
    ]);
    $entry->lines()->create(['account_id' => $this->accountClients->id, 'debit' => 25000, 'credit' => 0]);
    $entry->lines()->create(['account_id' => $this->accountVentes->id, 'debit' => 0, 'credit' => 25000]);

    $response = $this->postJson('/api/v1/accounting/financial-reports/ohada/income-statement', [
        'period' => now()->format('Y-m'),
    ]);

    $response->assertStatus(200);
    $data = $response->json('data');

    expect($data['report_type'])->toBe('compte_de_resultat_syscohada');
    expect((float) $data['totaux']['chiffre_affaires'])->toBe(25000.0);
    expect((float) $data['totaux']['resultat_net'])->toBe(25000.0);
});
