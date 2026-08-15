<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
            ->when($request->account_code, fn ($q) => $q->where('account_code', $request->account_code))
            ->orderByDesc('date')
            ->paginate(50);

        return response()->json($entries);
    }

    /** POST /journal-entries */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'         => 'required|date',
            'description'  => 'required|string|max:500',
            'currency'     => 'nullable|string|size:3',
            'lines'        => 'required|array|min:2',
            'lines.*.account_code' => 'required|string',
            'lines.*.debit'        => 'nullable|numeric|min:0',
            'lines.*.credit'       => 'nullable|numeric|min:0',
        ]);

        $entry = JournalEntry::create([
            'date'        => $validated['date'],
            'description' => $validated['description'],
            'currency'    => $validated['currency'] ?? 'XOF',
            'status'      => 'draft',
        ]);

        foreach ($validated['lines'] as $line) {
            $entry->lines()->create($line);
        }

        return response()->json(['data' => $entry->load('lines')], 201);
    }

    /** POST /journal-entries/{id}/post */
    public function post(int $id): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);
        $entry->update(['status' => 'posted', 'posted_at' => now()]);

        return response()->json(['data' => $entry]);
    }

    /** POST /journal-entries/{id}/reverse */
    public function reverse(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::findOrFail($id);

        $reversal = $entry->replicate();
        $reversal->description = 'REVERSAL: ' . $entry->description;
        $reversal->date        = $request->input('date', now()->toDateString());
        $reversal->status      = 'draft';
        $reversal->reversed_from_id = $entry->id;
        $reversal->save();

        foreach ($entry->lines as $line) {
            $reversed = $line->replicate();
            $reversed->journal_entry_id = $reversal->id;
            // Swap debit/credit
            $reversed->debit  = $line->credit;
            $reversed->credit = $line->debit;
            $reversed->save();
        }

        return response()->json(['data' => $reversal->load('lines')], 201);
    }
}
