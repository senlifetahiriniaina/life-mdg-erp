<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Services\TreasuryImportService;

/**
 * Chantier 15 — import d'opérations de caisse (encaissement/décaissement)
 * et de relevés bancaires, avec proposition automatique d'un modèle
 * d'opération comptable (`OperationTemplate`) par ligne avant validation.
 *
 * Deux étapes distinctes et volontairement sans effet de bord intermédiaire :
 * preview() ne persiste rien (l'utilisateur peut encore corriger le modèle
 * proposé par ligne) ; commit() est la seule action qui écrit réellement en
 * base, dans une transaction unique (tout ou rien).
 */
class TreasuryImportController extends Controller
{
    public function __construct(private TreasuryImportService $service) {}

    /** GET /accounting/treasury-accounts — every active class-5 (Trésorerie) account, e.g. for the caisse/banque picker on the import screen. */
    public function treasuryAccounts(): JsonResponse
    {
        return response()->json(['data' => $this->service->listTreasuryAccounts()]);
    }

    /** POST /accounting/treasury-imports/preview */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'treasury_account_code' => 'required|string',
        ]);

        if (! $this->service->isSupportedTreasuryAccount($validated['treasury_account_code'])) {
            return response()->json([
                'message' => "Compte de trésorerie non supporté : {$validated['treasury_account_code']}. Ce code ne correspond à aucun compte de trésorerie (classe 5) actif du plan comptable.",
            ], 422);
        }

        $tempPath = $request->file('file')->getRealPath();
        $parsed = $this->service->parseFile($tempPath);

        if ($parsed['rows'] === []) {
            return response()->json([
                'message' => "Aucune ligne exploitable détectée. Le fichier doit contenir des colonnes date / libellé / montant.",
                'headers' => $parsed['headers'],
                'rows' => [],
            ], 422);
        }

        $rows = $this->service->suggest($parsed['rows']);

        return response()->json([
            'headers' => $parsed['headers'],
            'row_count' => count($rows),
            'rows' => $rows,
        ]);
    }

    /** POST /accounting/treasury-imports/commit */
    public function commit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'treasury_account_code' => 'required|string',
            'bank_account_id' => 'nullable|integer|exists:acc_bank_accounts,id',
            'rows' => 'required|array|min:1',
            'rows.*.date' => 'required|date',
            'rows.*.description' => 'nullable|string|max:500',
            'rows.*.amount' => 'required|numeric',
            'rows.*.template_code' => 'required|string|exists:acc_operation_templates,code',
        ]);

        try {
            $result = $this->service->commit(
                $validated['rows'],
                $validated['treasury_account_code'],
                $validated['bank_account_id'] ?? null,
                $request->user()?->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result], 201);
    }
}
