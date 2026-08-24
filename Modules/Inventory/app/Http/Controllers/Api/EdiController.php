<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\EdiTransaction;
use Modules\Inventory\Services\EdiService;

/**
 * @group Inventory - EDI
 */
class EdiController extends Controller
{
    use ScopesToCompany;

    public function __construct(private readonly EdiService $edi) {}

    /**
     * POST /inventory/edi/receive
     * Receive raw EDI content, auto-detect transaction set, parse, and log.
     */
    public function receive(Request $request): JsonResponse
    {
        $request->validate([
            'content'    => 'required|string',
            'partner_id' => 'nullable|integer',
        ]);

        $raw       = $request->input('content');
        $partnerId = $request->input('partner_id');
        $txSet     = $this->edi->detectTransactionSet($raw);

        try {
            $parsed = match ($txSet) {
                '850'   => $this->edi->parse850($raw),
                '856'   => $this->edi->parse856($raw),
                default => ['raw_type' => $txSet, 'note' => 'Unsupported transaction set — stored as-is'],
            };
            $status = 'processed';
        } catch (\Throwable $e) {
            $parsed = ['error' => $e->getMessage()];
            $status = 'error';
        }

        $tx = EdiTransaction::create([
            'type'        => $txSet ?? 'unknown',
            'direction'   => 'inbound',
            'content_raw' => $raw,
            'parsed_json' => $parsed,
            'status'      => $status,
            'partner_id'  => $partnerId,
            'occurred_at' => now(),
            'company_id'  => $this->companyId($request),
        ]);

        return response()->json([
            'transaction_id' => $tx->id,
            'type'           => $txSet,
            'status'         => $status,
            'parsed'         => $parsed,
        ], 201);
    }

    /**
     * POST /inventory/edi/generate-810
     * Generate an X12 810 EDI Invoice string from structured data.
     */
    public function generate810(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => 'required|string|max:50',
            'invoice_date'   => 'nullable|date',
            'po_number'      => 'nullable|string|max:50',
            'currency'       => 'nullable|string|size:3',
            'sender_id'      => 'nullable|string|max:15',
            'receiver_id'    => 'nullable|string|max:15',
            'sender_gs'      => 'nullable|string|max:15',
            'receiver_gs'    => 'nullable|string|max:15',
            'total_amount'   => 'required|numeric|min:0',
            'tax_amount'     => 'nullable|numeric|min:0',
            'freight_amount' => 'nullable|numeric|min:0',
            'bill_from'      => 'nullable|array',
            'bill_to'        => 'nullable|array',
            'items'          => 'required|array|min:1',
            'items.*.sku'         => 'nullable|string',
            'items.*.description' => 'nullable|string',
            'items.*.quantity'    => 'required|numeric|min:0',
            'items.*.unit'        => 'nullable|string|max:5',
            'items.*.unit_price'  => 'required|numeric|min:0',
        ]);

        $ediString = $this->edi->generate810($data);

        EdiTransaction::create([
            'type'        => '810',
            'direction'   => 'outbound',
            'content_raw' => $ediString,
            'parsed_json' => $data,
            'status'      => 'processed',
            'partner_id'  => null,
            'occurred_at' => now(),
            'company_id'  => $this->companyId($request),
        ]);

        return response()->json([
            'edi' => $ediString,
        ]);
    }

    /**
     * GET /inventory/edi/transactions
     * List EDI transaction logs.
     */
    public function transactions(Request $request): JsonResponse
    {
        $txs = EdiTransaction::query()
            ->where('company_id', $this->companyId($request))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('direction'), fn ($q, $v) => $q->where('direction', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('occurred_at')
            ->paginate(20);

        return response()->json($txs);
    }
}
