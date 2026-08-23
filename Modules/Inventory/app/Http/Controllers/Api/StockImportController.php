<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\StockImportService;

/**
 * Chantier 16 — import de mouvements de stock (entrée/sortie) par fichier,
 * avec création automatique des produits inconnus dans le catalogue.
 *
 * preview() ne persiste rien (le fichier est seulement analysé et chaque
 * ligne rapprochée du catalogue existant) ; commit() est la seule action
 * qui écrit réellement en base, dans une transaction unique.
 */
class StockImportController extends Controller
{
    use ScopesToCompany;

    public function __construct(private StockImportService $service) {}

    /** POST /inventory/stock-imports/preview */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $tempPath = $request->file('file')->getRealPath();
        $parsed = $this->service->parseFile($tempPath);

        if ($parsed['rows'] === []) {
            return response()->json([
                'message' => 'Aucune ligne exploitable détectée. Le fichier doit contenir des colonnes produit (nom ou SKU) et quantité.',
                'headers' => $parsed['headers'],
                'rows' => [],
            ], 422);
        }

        return response()->json([
            'headers' => $parsed['headers'],
            'row_count' => count($parsed['rows']),
            'rows' => $this->service->preview($parsed['rows'], $this->companyId($request)),
        ]);
    }

    /** POST /inventory/stock-imports/commit */
    public function commit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'rows' => 'required|array|min:1',
            'rows.*.sku' => 'nullable|string',
            'rows.*.name' => 'required|string',
            'rows.*.quantity' => 'required|numeric|min:0.01',
            'rows.*.type' => 'required|in:in,out',
            'rows.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        // Chantier 32: verify the target warehouse actually belongs to the
        // caller's own company before importing anything into it — a real
        // record, not just an id, so this uses assertSameCompany rather
        // than a raw ->where() filter (which would silently produce a 404
        // via exists: validation instead of the correct 404-not-403 shape).
        $warehouse = Warehouse::findOrFail((int) $validated['warehouse_id']);
        $this->assertSameCompany($request, $warehouse);

        try {
            $result = $this->service->commit(
                $validated['rows'],
                (int) $validated['warehouse_id'],
                $request->user()?->id,
                $this->companyId($request),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result], 201);
    }
}
