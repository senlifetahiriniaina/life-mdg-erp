<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Warehouse;

/**
 * Chantier 16 — imports "mouvements de stock" (entrée/sortie) from a
 * CSV/Excel file into a chosen warehouse. Each row is matched against the
 * product catalogue by SKU (if given) or by name; a row naming a product
 * that doesn't exist yet gets that product created automatically — the
 * user's explicit ask ("les produits inscrits dans le motif seront
 * disponibles automatiquement à rajouter dans le catalogue") — under the
 * "Marchandises"/"Pièce" defaults `DefaultDataSeeder` already seeds
 * (Chantier 13), rather than inventing a new referential.
 *
 * Mirrors TreasuryImportService's shape (Chantier 15): preview() does no
 * writes at all, commit() is the only thing that persists, and it delegates
 * the actual stock-quantity update to InventoryService::recordMovement()
 * (the real, tested pessimistic-locked method — see the StockMovementController
 * fix in the same chantier for why that matters).
 */
class StockImportService
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * @return array{headers: list<string>, rows: list<array{sku: ?string, name: string, quantity: float, type: string, unit_cost: ?float}>}
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
        $skuKey = $this->findColumn($headers, ['sku', 'reference', 'ref', 'code']);
        $nameKey = $this->findColumn($headers, ['nom', 'designation', 'produit', 'name', 'article', 'libelle']);
        $qtyKey = $this->findColumn($headers, ['quantite', 'qte', 'quantity', 'qty']);
        $typeKey = $this->findColumn($headers, ['type', 'mouvement', 'sens']);
        $costKey = $this->findColumn($headers, ['prix', 'cout', 'unit_cost', 'cost']);

        $rows = [];
        foreach ($records as $record) {
            $record = array_pad($record, count($headers), null);
            $byHeader = array_combine($headers, array_slice($record, 0, count($headers)));

            $name = $nameKey !== null ? trim((string) ($byHeader[$nameKey] ?? '')) : '';
            $quantity = $qtyKey !== null ? $this->parseNumber($byHeader[$qtyKey] ?? null) : null;

            if ($name === '' || $quantity === null) {
                continue; // no usable product name or quantity — skip the row rather than guess
            }

            $rows[] = [
                'sku' => $skuKey !== null ? (trim((string) ($byHeader[$skuKey] ?? '')) ?: null) : null,
                'name' => $name,
                'quantity' => abs($quantity),
                'type' => $this->inferType($typeKey !== null ? (string) ($byHeader[$typeKey] ?? '') : '', $quantity),
                'unit_cost' => $costKey !== null ? $this->parseNumber($byHeader[$costKey] ?? null) : null,
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Read-only lookup — resolves each row against the real catalogue
     * without creating anything, so the user can review "N produits déjà
     * connus / N produits à créer" before committing.
     *
     * @param  list<array{sku: ?string, name: string, quantity: float, type: string, unit_cost: ?float}>  $rows
     * @return list<array{sku: ?string, name: string, quantity: float, type: string, unit_cost: ?float, product_exists: bool, matched_product_id: ?int}>
     */
    public function preview(array $rows, ?int $companyId = null): array
    {
        return array_map(function (array $row) use ($companyId) {
            $product = $this->findProduct($row['sku'], $row['name'], $companyId);

            return [
                ...$row,
                'product_exists' => $product !== null,
                'matched_product_id' => $product?->id,
            ];
        }, $rows);
    }

    /**
     * @param  list<array{sku: ?string, name: string, quantity: float, type: string, unit_cost: ?float}>  $rows
     * @return array{movements: int, products_created: list<array{id: int, sku: string, name: string}>}
     */
    public function commit(array $rows, int $warehouseId, ?int $userId, ?int $companyId = null): array
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        return DB::transaction(function () use ($rows, $warehouse, $userId, $companyId) {
            $productsCreated = [];
            $movementCount = 0;

            foreach ($rows as $row) {
                if (! in_array($row['type'], ['in', 'out'], true)) {
                    throw new \InvalidArgumentException("Type de mouvement invalide pour \"{$row['name']}\" : {$row['type']}");
                }

                $product = $this->findProduct($row['sku'], $row['name'], $companyId);

                if ($product === null) {
                    $product = $this->createProduct($row['sku'], $row['name'], $row['unit_cost'], $companyId);
                    $productsCreated[] = ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name];
                }

                try {
                    $this->inventoryService->recordMovement([
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                        'type' => $row['type'],
                        'quantity' => $row['quantity'],
                        'unit_cost' => $row['unit_cost'],
                        'reference_type' => 'stock_import',
                        'reason' => "Import de stock — {$row['name']}",
                        'created_by' => $userId,
                        'company_id' => $companyId,
                    ]);
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("{$row['name']} : {$e->getMessage()}");
                }

                $movementCount++;
            }

            return [
                'movements' => $movementCount,
                'products_created' => $productsCreated,
            ];
        });
    }

    private function findProduct(?string $sku, string $name, ?int $companyId = null): ?Product
    {
        // Chantier 32: scoped to the caller's own company — matching an
        // existing product by SKU/name from a *different* company would
        // otherwise silently attach this import's stock movements to a
        // record the caller has no real ownership of, and reuse its
        // cost_price/category as if it were their own catalogue entry.
        if ($sku !== null && $sku !== '') {
            $bySku = Product::where('sku', $sku)->where('company_id', $companyId)->first();
            if ($bySku !== null) {
                return $bySku;
            }
        }

        return Product::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->where('company_id', $companyId)
            ->first();
    }

    private function createProduct(?string $sku, string $name, ?float $unitCost, ?int $companyId = null): Product
    {
        $category = Category::firstOrCreate(['name' => 'Marchandises', 'company_id' => $companyId]);
        $unit = Unit::firstOrCreate(['name' => 'Pièce', 'company_id' => $companyId], ['symbol' => 'pc', 'type' => 'unit']);

        return Product::create([
            'sku' => $sku ?: $this->generateSku($name, $companyId),
            'name' => $name,
            'category_id' => $category->id,
            // `category`/`unit` are separate plain-string display columns on
            // inventory_products, distinct from the category_id/unit_id FKs
            // (confirmed via Schema::getColumnListing()) — ProductResource
            // reads $this->category directly rather than the relation, so
            // both need to be set for the product to show a category/unit
            // anywhere it's listed, not just when eager-loaded.
            'category' => $category->name,
            'unit_id' => $unit->id,
            'unit' => $unit->name,
            'type' => 'storable',
            'currency' => 'MGA',
            'cost_price' => $unitCost ?? 0,
            'sale_price' => 0,
            'is_active' => true,
            'company_id' => $companyId,
        ]);
    }

    private function generateSku(string $name, ?int $companyId = null): string
    {
        $base = Str::of($name)->slug()->upper()->limit(20, '')->value();
        $base = $base !== '' ? $base : 'PRODUIT';
        $sku = $base;
        $suffix = 1;

        while (Product::where('sku', $sku)->exists()) {
            $suffix++;
            $sku = "{$base}-{$suffix}";
        }

        return $sku;
    }

    private function inferType(string $rawType, float $signedQuantity): string
    {
        $normalized = Str::of($rawType)->lower()->ascii()->value();

        if (str_contains($normalized, 'sortie') || str_contains($normalized, 'out') || str_contains($normalized, 'decaiss')) {
            return 'out';
        }

        if (str_contains($normalized, 'entree') || str_contains($normalized, 'in') || str_contains($normalized, 'encaiss')) {
            return 'in';
        }

        // No explicit type column (or unrecognized value) — fall back to the sign of the quantity.
        return $signedQuantity < 0 ? 'out' : 'in';
    }

    // ─── File parsing helpers (mirrors TreasuryImportService) ──────────────

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

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', ' ', ','], ['', '', '.'], (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
