<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingService;

/**
 * @group Accounting - Journal Entries API
 *
 * CRUD and state transitions for journal entries (post, reverse).
 */
class JournalEntryApiController extends Controller
{
    public function __construct(private AccountingService $service) {}

    /** GET /journal-entries */
    public function index(Request $request): JsonResponse
    {
        $entries = JournalEntry::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->account_code, fn ($q) => $q->whereHas(
                'lines.account',
                fn ($q) => $q->where('code', $request->account_code)
            ))
            ->orderByDesc('date')
            ->paginate(50);

        return response()->json($entries);
    }

    /**
     * POST /journal-entries
     *
     * Chantier 15: `$entry->lines()->create($line)` was a fatal error (no
     * `lines()` relation existed — fixed on the model), and `account_code`
     * was validated but never resolved to the real `account_id` the
     * `acc_journal_entry_lines.account_id` FK actually needs — every line
     * would have failed mass assignment against `JournalEntryLine::$fillable`
     * silently dropping the account link. Also enforces a balanced entry
     * (sum of debits == sum of credits), the one invariant double-entry
     * bookkeeping can't skip.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'         => 'required|date',
            'description'  => 'required|string|max:500',
            'currency'     => 'nullable|string|size:3',
            'lines'        => 'required|array|min:2',
            'lines.*.account_code' => 'required|string|exists:acc_chart_of_accounts,code',
            'lines.*.description'  => 'nullable|string|max:500',
            'lines.*.debit'        => 'nullable|numeric|min:0',
            'lines.*.credit'       => 'nullable|numeric|min:0',
        ]);

        $totalDebit = array_sum(array_column($validated['lines'], 'debit'));
        $totalCredit = array_sum(array_column($validated['lines'], 'credit'));

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return response()->json([
                'message' => 'L\'écriture n\'est pas équilibrée : le total des débits doit être égal au total des crédits.',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ], 422);
        }

        // Chantier 32 (volet A1) — résout l'exercice comptable couvrant la
        // date de l'écriture. Jamais bloquant si aucun exercice ne couvre
        // la date (fallback-first, cohérent avec le reste de l'app) :
        // l'écriture se poste quand même, simplement sans exercice lié.
        $fiscalYear = FiscalYear::coveringDate($validated['date'], $request->user()?->company_id);

        $entry = JournalEntry::create([
            'date'           => $validated['date'],
            'entry_date'     => $validated['date'],
            'description'    => $validated['description'],
            'currency'       => $validated['currency'] ?? 'MGA',
            'status'         => 'draft',
            'fiscal_year_id' => $fiscalYear?->id,
        ]);

        foreach ($validated['lines'] as $line) {
            $accountId = ChartOfAccount::where('code', $line['account_code'])->value('id');

            $entry->lines()->create([
                'account_id'  => $accountId,
                'description' => $line['description'] ?? null,
                'debit'       => $line['debit'] ?? 0,
                'credit'      => $line['credit'] ?? 0,
            ]);
        }

        return response()->json(['data' => $entry->load('lines.account')], 201);
    }

    /** POST /journal-entries/{id}/post */
    public function post(int $id): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);
        $entry->update(['status' => 'posted', 'posted_at' => now()]);

        return response()->json(['data' => $entry]);
    }

    /**
     * POST /journal-entries/{id}/reverse
     *
     * Chantier 15: fixed the same `lines()` fatal error, plus two field-name
     * bugs — `journal_entry_id` doesn't exist on `acc_journal_entry_lines`
     * (the real FK is `entry_id`), and `reversed_from_id` doesn't exist on
     * `acc_journal_entries` at all (a guaranteed SQL error on `->save()`).
     * The already-real `reference_type`/`reference_id` morph columns link
     * the reversal back to the original entry instead.
     */
    public function reverse(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::with('lines')->findOrFail($id);

        $reversal = $entry->replicate(['entry_number']);
        $reversal->description    = 'REVERSAL: ' . $entry->description;
        $reversal->date           = $request->input('date', now()->toDateString());
        $reversal->entry_date     = $reversal->date;
        $reversal->status         = 'draft';
        $reversal->reference_type = JournalEntry::class;
        $reversal->reference_id   = $entry->id;
        $reversal->save();

        foreach ($entry->lines as $line) {
            $reversal->lines()->create([
                'account_id'  => $line->account_id,
                'description' => $line->description,
                'debit'       => $line->credit,
                'credit'      => $line->debit,
            ]);
        }

        return response()->json(['data' => $reversal->load('lines.account')], 201);
    }
}
