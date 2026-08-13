<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @group Accounting - Ledger Matching (Lettrage)
 */
class LedgerMatchingController extends Controller
{
    /**
     * List unmatched invoice lines (open items for lettrage).
     *
     * @queryParam account_code string Filter by account code prefix. Example: 411
     */
    public function index(Request $request): JsonResponse
    {
        $lines = DB::table('acc_invoice_lines as il')
            ->join('acc_invoices as i', 'i.id', '=', 'il.invoice_id')
            ->join('acc_chart_of_accounts as coa', 'coa.id', '=', 'il.account_id')
            ->whereNull('il.match_ref')
            ->where('i.status', '!=', 'draft')
            ->when($request->account_code, fn ($q) => $q->where('coa.code', 'like', "{$request->account_code}%"))
            ->select([
                'il.id', 'il.invoice_id', 'i.number as invoice_number', 'i.type as invoice_type',
                'i.partner_name', 'il.account_id', 'coa.code as account_code', 'coa.name as account_name',
                'il.quantity', 'il.unit_price', 'il.total', 'il.matched_at',
            ])
            ->paginate($request->integer('per_page', 50));

        return response()->json($lines);
    }

    /**
     * Match a set of invoice lines together (lettrage).
     * Lines are linked with a shared match_ref (UUID).
     */
    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'line_ids' => ['required', 'array', 'min:2'],
            'line_ids.*' => ['required', 'integer', 'exists:acc_invoice_lines,id'],
        ]);

        $matchRef = Str::uuid()->toString();
        $now = now();
        $userId = $request->user()->id;

        DB::table('acc_invoice_lines')
            ->whereIn('id', $validated['line_ids'])
            ->update([
                'match_ref' => $matchRef,
                'matched_by' => $userId,
                'matched_at' => $now,
            ]);

        return response()->json([
            'match_ref' => $matchRef,
            'matched' => count($validated['line_ids']),
            'matched_at' => $now->toIso8601String(),
        ]);
    }

    /**
     * Unmatch (délettrage) a set of lines sharing the same match_ref.
     */
    public function unmatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'match_ref' => ['required', 'string'],
        ]);

        $count = DB::table('acc_invoice_lines')
            ->where('match_ref', $validated['match_ref'])
            ->update([
                'match_ref' => null,
                'matched_by' => null,
                'matched_at' => null,
            ]);

        return response()->json(['unmatched' => $count]);
    }
}
