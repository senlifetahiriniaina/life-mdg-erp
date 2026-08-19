<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\OperationTemplate;

/**
 * Chantier 15 — imports "opérations de caisse" (cash-register encaissement/
 * décaissement) and relevés bancaires from a CSV/Excel file, proposes a
 * default `OperationTemplate` per row (deterministic French-keyword
 * matching — no AI dependency, works offline, matches this app's
 * fallback-first "AI Assisted First" design), and on commit turns each
 * validated row into a real, balanced JournalEntry + 2 JournalEntryLine
 * (debiting/crediting the treasury account against the template's
 * counterpart GL account), in the CAI (caisse) or BNQ (banque/mobile
 * money) journal depending on which treasury account is being imported for.
 *
 * The exact same flow serves both cash and bank imports — only the
 * `treasuryAccountCode` differs (530 Caisse vs. 512 Banque / 531 Mvola /
 * 532 Airtel Money) — matching the user's own framing ("de la même
 * manière"). For a bank-like treasury account, commit() also creates a
 * BankStatement + pre-matched BankTransaction via the existing
 * BankReconciliationService, so the new entries show up in the app's real
 * bank-reconciliation screens instead of only living in the journal.
 */
class TreasuryImportService
{
    /** Treasury account codes this feature accepts, and which journal each posts to. */
    private const TREASURY_JOURNALS = [
        '530' => 'CAI',
        '512' => 'BNQ',
        '531' => 'BNQ',
        '532' => 'BNQ',
    ];

    public function __construct(private BankReconciliationService $bankReconciliationService) {}

    public function isSupportedTreasuryAccount(string $code): bool
    {
        return isset(self::TREASURY_JOURNALS[$code]);
    }

    /** @return \Illuminate\Support\Collection<int, OperationTemplate> */
    public function listTemplates(): \Illuminate\Support\Collection
    {
        return OperationTemplate::query()->active()->orderBy('nature')->orderBy('label')->get();
    }

    /**
     * @return array{headers: list<string>, rows: list<array{date: string, description: string, amount: float}>}
     */
    public function parseFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $records = $ext === 'csv' || $ext === 'txt'
            ? $this->parseCsv($path)
            : $this->parseSpreadsheet($path);

        if ($records === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn ($h) => Str::of((string) $h)->lower()->ascii()->trim()->value(), array_shift($records));
        $dateKey = $this->findColumn($headers, ['date']);
        $descriptionKey = $this->findColumn($headers, ['libelle', 'description', 'intitule', 'motif']);
        $amountKey = $this->findColumn($headers, ['montant', 'amount']);

        $rows = [];
        foreach ($records as $record) {
            $record = array_pad($record, count($headers), null);
            $byHeader = array_combine($headers, array_slice($record, 0, count($headers)));

            $amount = $amountKey !== null ? $this->parseAmount($byHeader[$amountKey] ?? null) : null;
            if ($amount === null) {
                continue; // no usable amount — skip the row rather than guess
            }

            $rows[] = [
                'date' => $dateKey !== null ? $this->parseDate($byHeader[$dateKey] ?? null) : now()->toDateString(),
                'description' => $descriptionKey !== null ? trim((string) ($byHeader[$descriptionKey] ?? '')) : '',
                'amount' => $amount,
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Suggests a template per row (best match + up to 2 alternatives),
     * never leaving a row without a suggestion — falls back to the generic
     * "autre_produit"/"autre_charge" template for the row's inferred nature.
     *
     * @param  list<array{date: string, description: string, amount: float}>  $rows
     * @return list<array{date: string, description: string, amount: float, nature: string, suggested_template_code: string, confidence: float, alternatives: list<string>}>
     */
    public function suggest(array $rows): array
    {
        $templates = $this->listTemplates();

        return array_map(function (array $row) use ($templates) {
            $nature = $row['amount'] >= 0 ? 'encaissement' : 'decaissement';
            $normalizedDescription = $this->normalize($row['description']);

            $scored = $templates
                ->where('nature', $nature)
                ->map(function (OperationTemplate $template) use ($normalizedDescription) {
                    $hits = collect($template->keywords ?? [])
                        ->filter(fn ($keyword) => $normalizedDescription !== '' && $this->matchesKeyword($normalizedDescription, $keyword))
                        ->count();

                    return ['template' => $template, 'hits' => $hits];
                })
                ->filter(fn ($s) => $s['hits'] > 0)
                ->sortByDesc('hits')
                ->values();

            if ($scored->isEmpty()) {
                $fallbackCode = $nature === 'encaissement' ? 'autre_produit' : 'autre_charge';

                return [
                    ...$row,
                    'nature' => $nature,
                    'suggested_template_code' => $fallbackCode,
                    'confidence' => 0.2,
                    'alternatives' => [],
                ];
            }

            $best = $scored->first();
            $maxHits = max(1, $best['hits']);

            return [
                ...$row,
                'nature' => $nature,
                'suggested_template_code' => $best['template']->code,
                'confidence' => min(1.0, 0.5 + 0.25 * $best['hits']),
                'alternatives' => $scored->slice(1, 2)->pluck('template.code')->values()->all(),
            ];
        }, $rows);
    }

    /**
     * @param  list<array{date: string, description: string, amount: float, template_code: string}>  $rows
     * @return array{entries: list<int>, count: int, total_debit: float, total_credit: float}
     */
    public function commit(array $rows, string $treasuryAccountCode, ?int $bankAccountId, ?int $userId): array
    {
        if (! $this->isSupportedTreasuryAccount($treasuryAccountCode)) {
            throw new \InvalidArgumentException("Compte de trésorerie non supporté : {$treasuryAccountCode}");
        }

        $treasuryAccount = ChartOfAccount::where('code', $treasuryAccountCode)->firstOrFail();
        $journal = Journal::where('code', self::TREASURY_JOURNALS[$treasuryAccountCode])->firstOrFail();

        $templatesByCode = $this->listTemplates()->keyBy('code');
        $accountsByCode = ChartOfAccount::whereIn('code', $templatesByCode->pluck('counterpart_account_code')->unique())
            ->get()->keyBy('code');

        $bankAccount = null;
        if ($treasuryAccountCode !== '530' && $bankAccountId !== null) {
            $bankAccount = BankAccount::findOrFail($bankAccountId);
        }

        return DB::transaction(function () use ($rows, $treasuryAccount, $journal, $templatesByCode, $accountsByCode, $bankAccount, $userId) {
            $sequence = JournalEntry::where('journal_id', $journal->id)->count();
            $entryIds = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            $bankRows = [];

            foreach ($rows as $row) {
                $template = $templatesByCode->get($row['template_code'] ?? null);
                if ($template === null) {
                    throw new \InvalidArgumentException("Modèle d'opération inconnu : " . ($row['template_code'] ?? '(vide)'));
                }

                $counterpartAccount = $accountsByCode->get($template->counterpart_account_code);
                if ($counterpartAccount === null) {
                    throw new \InvalidArgumentException("Compte de contrepartie introuvable pour le modèle {$template->code}.");
                }

                $amount = round(abs((float) $row['amount']), 2);
                $sequence++;

                $entry = JournalEntry::create([
                    'entry_number' => sprintf('%s-%s-%04d', $journal->code, \Carbon\Carbon::parse($row['date'])->format('Ym'), $sequence),
                    'date' => $row['date'],
                    'entry_date' => $row['date'],
                    'description' => trim(($row['description'] ?? '') . ' — ' . $template->label),
                    'currency' => 'MGA',
                    'journal_id' => $journal->id,
                    'status' => 'posted',
                    'posted_at' => now(),
                    'created_by' => $userId,
                ]);

                if ($template->isEncaissement()) {
                    $entry->lines()->create(['account_id' => $treasuryAccount->id, 'description' => $template->label, 'debit' => $amount, 'credit' => 0]);
                    $entry->lines()->create(['account_id' => $counterpartAccount->id, 'description' => $template->label, 'debit' => 0, 'credit' => $amount]);
                } else {
                    $entry->lines()->create(['account_id' => $counterpartAccount->id, 'description' => $template->label, 'debit' => $amount, 'credit' => 0]);
                    $entry->lines()->create(['account_id' => $treasuryAccount->id, 'description' => $template->label, 'debit' => 0, 'credit' => $amount]);
                }

                $totalDebit += $amount;
                $totalCredit += $amount;
                $entryIds[] = $entry->id;

                if ($bankAccount !== null) {
                    $bankRows[] = [
                        'entry_id' => $entry->id,
                        'date' => $row['date'],
                        'description' => $row['description'] ?: $template->label,
                        'amount' => $template->isEncaissement() ? $amount : -$amount,
                    ];
                }
            }

            if ($bankAccount !== null && $bankRows !== []) {
                $this->attachBankStatement($bankAccount, $bankRows);
            }

            return [
                'entries' => $entryIds,
                'count' => count($entryIds),
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
            ];
        });
    }

    private function attachBankStatement(BankAccount $bankAccount, array $bankRows): void
    {
        $netMovement = array_sum(array_column($bankRows, 'amount'));
        $opening = (float) $bankAccount->current_balance;

        $statement = $this->bankReconciliationService->importStatement($bankAccount, [
            'statement_date' => now()->toDateString(),
            'opening_balance' => $opening,
            'closing_balance' => round($opening + $netMovement, 2),
            'notes' => "Import automatique — opérations de caisse/banque (Chantier 15)",
        ], $bankRows);

        // The transaction that just funded/received each newly-created
        // journal entry is already reconciled — pre-match it instead of
        // leaving it for the manual/auto-match screens to pick up.
        $statement->transactions()->orderBy('id')->get()->each(function ($transaction, int $index) use ($bankRows) {
            $transaction->match($bankRows[$index]['entry_id']);
        });
        $statement->update(['matched_count' => count($bankRows)]);

        $bankAccount->update(['current_balance' => round($opening + $netMovement, 2)]);
    }

    // ─── File parsing helpers ──────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = str_contains((string) $firstLine, ';') ? ';' : ',';

        $records = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $records[] = $row;
        }
        fclose($handle);

        return $records;
    }

    private function parseSpreadsheet(string $path): array
    {
        if (! class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            return [];
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    private function findColumn(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            foreach ($headers as $header) {
                if (str_contains($header, $candidate)) {
                    return $header;
                }
            }
        }

        return null;
    }

    private function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', ' ', ','], ['', '', '.'], (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function parseDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return now()->toDateString();
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->value();
    }

    /**
     * Whole-word/phrase match on the already-normalized description —
     * plain str_contains() would let a short keyword like "paie" falsely
     * match inside an unrelated word like "paiement" (confirmed while
     * testing: it made "Paiement fournisseur ..." suggest the salary
     * template instead of the supplier-payment one).
     */
    private function matchesKeyword(string $normalizedDescription, string $keyword): bool
    {
        $pattern = '/\b' . preg_quote($this->normalize($keyword), '/') . '\b/u';

        return (bool) preg_match($pattern, $normalizedDescription);
    }
}
