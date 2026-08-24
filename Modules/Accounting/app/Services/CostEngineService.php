<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Accounting\Models\CostEntry;
use Modules\Accounting\Models\CostRollup;

/**
 * CostEngineService — CAPEX / OPEX / FINEX / RISKEX cost engine.
 *
 * Handles:
 *   - Recording atomic cost entries
 *   - Computing per-entity cost breakdowns at four granularity levels:
 *       BOM component → product → project → client
 *   - Rolling up costs into cached CostRollup aggregates
 *   - Importing costs from other modules (Achats, Accounting, HR, Logistics,
 *     Manufacturing)
 *   - AI-powered cost analysis via Claude
 *
 * OHADA account mapping (static):
 *   CAPEX   → Classe 2  (2818, 2828, 6813, 6814)
 *   OPEX    → Classe 6  (601, 602, 604, 641, 658)
 *   FINEX   → Classe 67 (671, 672, 673, 676)
 *   RISKEX  → Classe 69 (691, 694, 697)
 */
class CostEngineService
{
    /** OHADA chart-of-accounts mapping. */
    /**
     * Chantier 36 — remapped onto the real user-provided chart of
     * accounts. Confirmed dead code (no consumer anywhere in the repo
     * besides a docblock comment in an Inventory migration) — corrected
     * for consistency rather than left stale, on the same "close the
     * landmine before a future caller trips it" precedent used elsewhere
     * in this session.
     */
    public const OHADA_ACCOUNT_MAP = [
        'CAPEX'  => [
            'class'    => '2',
            'accounts' => ['28', '29', '68'],
            'label'    => 'Immobilisations / Amortissements',
        ],
        'OPEX'   => [
            'class'    => '6',
            'accounts' => ['601', '602', '604', '661', '658'],
            'label'    => 'Charges d\'exploitation',
        ],
        'FINEX'  => [
            'class'    => '67',
            'accounts' => ['671', '676'],
            'label'    => 'Frais financiers',
        ],
        'RISKEX' => [
            'class'    => '69',
            'accounts' => ['69'],
            'label'    => 'Dotations aux provisions / Charges HAO',
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────
    // ENTRY RECORDING
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Record a cost entry with automatic allocation.
     *
     * Required keys in $data:
     *   category_code, amount, currency, allocatable_type, allocatable_id
     *
     * Optional:
     *   source_module, source_type, source_id, description,
     *   period (defaults to current month), cost_driver, units
     */
    public function recordCost(array $data): CostEntry
    {
        if (! isset($data['period'])) {
            $data['period'] = Carbon::now()->format('Y-m');
        }
        if (! isset($data['fiscal_year'])) {
            $data['fiscal_year'] = substr($data['period'], 0, 4);
        }

        // Convert to XOF if needed (basic fallback — real conversion via MultiCurrencyService)
        if (! isset($data['amount_xof'])) {
            $data['amount_xof'] = $this->convertToXof(
                (float) $data['amount'],
                $data['currency'] ?? 'XOF'
            );
        }

        $data['created_by'] = $data['created_by'] ?? auth()->id();

        $entry = CostEntry::create($data);

        // Invalidate rollup cache for this entity
        $this->invalidateRollup(
            $data['allocatable_type'],
            (int) $data['allocatable_id'],
            (int) ($data['tenant_id'] ?? $entry->tenant_id),
            $data['period']
        );

        return $entry;
    }

    // ─────────────────────────────────────────────────────────────────────
    // COST QUERIES — four granularity levels
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Costs for a single BOM component (identified by SKU) for a period.
     */
    public function getComponentCost(string $componentSku, string $period, int $tenantId): array
    {
        return $this->getEntityCostByKey('bom_component', $componentSku, $period, $tenantId, 'sku');
    }

    /**
     * Costs for a product (sum of all BOM component costs + assembly costs).
     */
    public function getProductCost(int $productId, string $period, int $tenantId): array
    {
        return $this->getEntityCost('product', $productId, $period, $tenantId);
    }

    /**
     * Total costs for a project.
     */
    public function getProjectCost(int $projectId, string $period, int $tenantId): array
    {
        return $this->getEntityCost('project', $projectId, $period, $tenantId);
    }

    /**
     * Total costs for a client (all products/projects linked to this client).
     */
    public function getClientCost(int $clientId, string $period, int $tenantId): array
    {
        return $this->getEntityCost('client', $clientId, $period, $tenantId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ROLLUP COMPUTATION
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Compute and persist the cost rollup for an entity.
     * Called on-demand; the result is cached in cost_rollups.
     */
    public function rollupCosts(string $entityType, int $entityId, int $tenantId): CostRollup
    {
        $now    = Carbon::now();
        $period = $now->format('Y-m');

        $totals = CostEntry::query()
            ->forTenant($tenantId)
            ->forEntity($entityType, $entityId)
            ->select(
                'category_code',
                DB::raw('SUM(amount_xof) as total'),
                DB::raw('SUM(units) as total_units'),
            )
            ->groupBy('category_code')
            ->get()
            ->keyBy('category_code');

        $capex  = (float) ($totals['CAPEX']?->total  ?? 0);
        $opex   = (float) ($totals['OPEX']?->total   ?? 0);
        $finex  = (float) ($totals['FINEX']?->total  ?? 0);
        $riskex = (float) ($totals['RISKEX']?->total ?? 0);
        $total  = $capex + $opex + $finex + $riskex;

        $totalUnits = (float) CostEntry::query()
            ->forTenant($tenantId)
            ->forEntity($entityType, $entityId)
            ->sum('units');

        $rollup = CostRollup::updateOrCreate(
            [
                'tenant_id'   => $tenantId,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'period'      => $period,
            ],
            [
                'capex_total'  => $capex,
                'opex_total'   => $opex,
                'finex_total'  => $finex,
                'riskex_total' => $riskex,
                'total_cost'   => $total,
                'currency'     => 'XOF',
                'unit_cost'    => $totalUnits > 0 ? round($total / $totalUnits, 6) : null,
                'computed_at'  => $now,
            ]
        );

        return $rollup;
    }

    /**
     * Calculate margin = selling_price - total_cost.
     */
    public function calculateMargin(string $entityType, int $entityId, int $tenantId): array
    {
        $period  = Carbon::now()->format('Y-m');
        $rollup  = CostRollup::query()
            ->forTenant($tenantId)
            ->forEntity($entityType, $entityId)
            ->forPeriod($period)
            ->first();

        if (! $rollup) {
            $rollup = $this->rollupCosts($entityType, $entityId, $tenantId);
        }

        // Retrieve selling price from the appropriate module
        $sellingPrice = $this->getSellingPrice($entityType, $entityId, $tenantId);

        $margin    = $sellingPrice - $rollup->total_cost;
        $marginPct = $sellingPrice > 0 ? round(($margin / $sellingPrice) * 100, 2) : 0;

        // Persist margin on rollup
        $rollup->update([
            'margin'     => $margin,
            'margin_pct' => $marginPct,
        ]);

        return [
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'selling_price' => $sellingPrice,
            'total_cost'    => $rollup->total_cost,
            'margin'        => $margin,
            'margin_pct'    => $marginPct,
            'capex_total'   => $rollup->capex_total,
            'opex_total'    => $rollup->opex_total,
            'finex_total'   => $rollup->finex_total,
            'riskex_total'  => $rollup->riskex_total,
            'currency'      => $rollup->currency,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // MODULE IMPORTS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Import costs from existing ERP modules.
     *
     * Supported modules:
     *   Achats       — purchase order line items → CAPEX or OPEX per component
     *   Accounting   — invoices → allocate to products/projects/clients
     *   HR           — labor hours (Timesheets) → OPEX per project
     *   Logistics    — freight costs → OPEX + FINEX (if financed)
     *   Manufacturing — depreciation → CAPEX per product
     */
    public function importFromModule(string $module, int $sourceId, int $tenantId): array
    {
        return match ($module) {
            'Achats'         => $this->importFromAchats($sourceId, $tenantId),
            'Accounting'     => $this->importFromAccounting($sourceId, $tenantId),
            'HR'             => $this->importFromHR($sourceId, $tenantId),
            'Logistics'      => $this->importFromLogistics($sourceId, $tenantId),
            'Manufacturing'  => $this->importFromManufacturing($sourceId, $tenantId),
            default          => ['imported' => 0, 'error' => "Unknown module: {$module}"],
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // AI ANALYSIS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * AI-powered cost analysis: compare actual vs budget vs industry benchmark.
     * Uses Claude with prompt caching. Degrades gracefully when API key is absent.
     */
    public function aiAnalyzeCosts(string $entityType, int $entityId, int $tenantId): string
    {
        $period = Carbon::now()->format('Y-m');

        $rollup = CostRollup::query()
            ->forTenant($tenantId)
            ->forEntity($entityType, $entityId)
            ->forPeriod($period)
            ->first();

        if (! $rollup) {
            $rollup = $this->rollupCosts($entityType, $entityId, $tenantId);
        }

        $snapshot = [
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'period'       => $period,
            'capex'        => $rollup->capex_total,
            'opex'         => $rollup->opex_total,
            'finex'        => $rollup->finex_total,
            'riskex'       => $rollup->riskex_total,
            'total'        => $rollup->total_cost,
            'margin_pct'   => $rollup->margin_pct,
            'currency'     => $rollup->currency,
        ];

        $apiKey = config('services.anthropic.key');
        if (! $apiKey) {
            return $this->staticCostAnalysisFallback($snapshot);
        }

        $systemPrompt = <<<PROMPT
You are an expert ERP cost controller specialised in OHADA/SYSCOHADA accounting for African SMEs.
Analyse the cost structure provided and:
1. Identify if FINEX (financial costs) is abnormally high (>12% of total = BFR problem)
2. Flag if RISKEX (risk provisions) exceeds 8% of total (systemic quality issues)
3. Compare to industry benchmarks (manufacturing target: OPEX 55-65%, CAPEX 20-30%, FINEX <8%, RISKEX <5%)
4. Give 3 concrete cost-reduction recommendations in French
5. Map to OHADA account classes (Classe 2/6/67/69)
Return a brief, actionable narrative in French (max 200 words). No JSON.
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 800,
                'system'     => [
                    [
                        'type'          => 'text',
                        'text'          => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => 'Analyse cette structure de coûts: ' . json_encode($snapshot),
                    ],
                ],
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text', $this->staticCostAnalysisFallback($snapshot));
            }
        } catch (\Throwable) {
            // Fall through to static fallback
        }

        return $this->staticCostAnalysisFallback($snapshot);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SUMMARY
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Aggregate cost summary for a tenant for a given period.
     * Groups by entity_type and category_code.
     */
    public function getSummary(int $tenantId, string $period): array
    {
        $totals = CostEntry::query()
            ->forTenant($tenantId)
            ->forPeriod($period)
            ->select(
                'category_code',
                DB::raw('SUM(amount_xof) as total'),
                DB::raw('COUNT(*) as entries'),
            )
            ->groupBy('category_code')
            ->get()
            ->keyBy('category_code');

        $capex  = (float) ($totals['CAPEX']?->total  ?? 0);
        $opex   = (float) ($totals['OPEX']?->total   ?? 0);
        $finex  = (float) ($totals['FINEX']?->total  ?? 0);
        $riskex = (float) ($totals['RISKEX']?->total ?? 0);
        $total  = $capex + $opex + $finex + $riskex;

        return [
            'period'   => $period,
            'currency' => 'XOF',
            'CAPEX'    => ['total' => $capex,  'entries' => (int) ($totals['CAPEX']?->entries  ?? 0)],
            'OPEX'     => ['total' => $opex,   'entries' => (int) ($totals['OPEX']?->entries   ?? 0)],
            'FINEX'    => ['total' => $finex,  'entries' => (int) ($totals['FINEX']?->entries  ?? 0)],
            'RISKEX'   => ['total' => $riskex, 'entries' => (int) ($totals['RISKEX']?->entries ?? 0)],
            'total'    => $total,
            'structure_pct' => $total > 0 ? [
                'CAPEX'  => round(($capex  / $total) * 100, 1),
                'OPEX'   => round(($opex   / $total) * 100, 1),
                'FINEX'  => round(($finex  / $total) * 100, 1),
                'RISKEX' => round(($riskex / $total) * 100, 1),
            ] : ['CAPEX' => 0, 'OPEX' => 0, 'FINEX' => 0, 'RISKEX' => 0],
        ];
    }

    /**
     * Paginated cost entries grouped by entity type for the list views.
     */
    public function getRollupsByEntityType(
        int $tenantId,
        string $entityType,
        string $period,
        int $perPage = 20
    ) {
        return CostRollup::query()
            ->forTenant($tenantId)
            ->entityType($entityType)
            ->forPeriod($period)
            ->orderByDesc('total_cost')
            ->paginate($perPage);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function getEntityCost(string $type, int $id, string $period, int $tenantId): array
    {
        // Try cached rollup first
        $rollup = CostRollup::query()
            ->forTenant($tenantId)
            ->forEntity($type, $id)
            ->forPeriod($period)
            ->first();

        if ($rollup && $rollup->computed_at && $rollup->computed_at->gt(Carbon::now()->subMinutes(5))) {
            return $this->formatRollupResult($rollup);
        }

        // Compute from entries
        $rows = CostEntry::query()
            ->forTenant($tenantId)
            ->forEntity($type, $id)
            ->forPeriod($period)
            ->select('category_code', DB::raw('SUM(amount_xof) as total'), DB::raw('SUM(units) as units'))
            ->groupBy('category_code')
            ->get()
            ->keyBy('category_code');

        $capex  = (float) ($rows['CAPEX']?->total  ?? 0);
        $opex   = (float) ($rows['OPEX']?->total   ?? 0);
        $finex  = (float) ($rows['FINEX']?->total  ?? 0);
        $riskex = (float) ($rows['RISKEX']?->total ?? 0);
        $total  = $capex + $opex + $finex + $riskex;
        $units  = (float) ($rows->first()?->units ?? 0);

        return [
            'entity_type' => $type,
            'entity_id'   => $id,
            'period'      => $period,
            'currency'    => 'XOF',
            'CAPEX'       => $capex,
            'OPEX'        => $opex,
            'FINEX'       => $finex,
            'RISKEX'      => $riskex,
            'total'       => $total,
            'unit_cost'   => $units > 0 ? round($total / $units, 6) : null,
        ];
    }

    private function getEntityCostByKey(
        string $type,
        string $key,
        string $period,
        int $tenantId,
        string $keyColumn
    ): array {
        // Resolve entity_id from external table (e.g. bom_components.sku)
        $id = DB::table('bom_components')
            ->where('tenant_id', $tenantId)
            ->where($keyColumn, $key)
            ->value('id');

        if (! $id) {
            return [
                'entity_type' => $type,
                'entity_id'   => null,
                'period'      => $period,
                'currency'    => 'XOF',
                'CAPEX'       => 0,
                'OPEX'        => 0,
                'FINEX'       => 0,
                'RISKEX'      => 0,
                'total'       => 0,
                'unit_cost'   => null,
            ];
        }

        return $this->getEntityCost($type, (int) $id, $period, $tenantId);
    }

    private function formatRollupResult(CostRollup $rollup): array
    {
        return [
            'entity_type' => $rollup->entity_type,
            'entity_id'   => $rollup->entity_id,
            'entity_name' => $rollup->entity_name,
            'period'      => $rollup->period,
            'currency'    => $rollup->currency,
            'CAPEX'       => $rollup->capex_total,
            'OPEX'        => $rollup->opex_total,
            'FINEX'       => $rollup->finex_total,
            'RISKEX'      => $rollup->riskex_total,
            'total'       => $rollup->total_cost,
            'unit_cost'   => $rollup->unit_cost,
            'margin'      => $rollup->margin,
            'margin_pct'  => $rollup->margin_pct,
        ];
    }

    private function invalidateRollup(string $type, int $id, int $tenantId, string $period): void
    {
        CostRollup::query()
            ->forTenant($tenantId)
            ->forEntity($type, $id)
            ->forPeriod($period)
            ->update(['computed_at' => null]);
    }

    private function convertToXof(float $amount, string $currency): float
    {
        if ($currency === 'XOF' || $currency === 'XAF') {
            return $amount;
        }

        // Basic hardcoded fallback rates (production would use MultiCurrencyService)
        $rates = [
            'EUR' => 655.957,
            'USD' => 600.0,
            'GBP' => 760.0,
            'NGN' => 0.38,
            'KES' => 4.5,
            'GHS' => 40.0,
        ];

        return $amount * ($rates[$currency] ?? 600.0);
    }

    private function getSellingPrice(string $entityType, int $entityId, int $tenantId): float
    {
        return match ($entityType) {
            'product' => (float) DB::table('products')
                ->where('id', $entityId)
                ->value('selling_price') ?? 0.0,
            'project' => (float) DB::table('projects')
                ->where('id', $entityId)
                ->value('budget') ?? 0.0,
            default   => 0.0,
        };
    }

    // ─── Module-specific importers ────────────────────────────────────────

    private function importFromAchats(int $purchaseOrderId, int $tenantId): array
    {
        $lines = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $purchaseOrderId)
            ->get();

        $imported = 0;
        foreach ($lines as $line) {
            $this->recordCost([
                'tenant_id'        => $tenantId,
                'category_code'    => $this->classifyAchatLine((array) $line),
                'amount'           => $line->total_price ?? $line->unit_price * $line->quantity,
                'currency'         => $line->currency ?? 'XOF',
                'allocatable_type' => 'product',
                'allocatable_id'   => $line->product_id ?? 0,
                'source_module'    => 'Achats',
                'source_type'      => 'purchase_order',
                'source_id'        => $purchaseOrderId,
                'description'      => "Import Achats PO#{$purchaseOrderId}: " . ($line->description ?? ''),
                'cost_driver'      => 'per_unit',
                'units'            => $line->quantity ?? 1,
            ]);
            $imported++;
        }

        return ['module' => 'Achats', 'source_id' => $purchaseOrderId, 'imported' => $imported];
    }

    private function importFromAccounting(int $invoiceId, int $tenantId): array
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
        if (! $invoice) {
            return ['imported' => 0, 'error' => 'Invoice not found'];
        }

        $this->recordCost([
            'tenant_id'        => $tenantId,
            'category_code'    => 'OPEX',
            'amount'           => $invoice->total ?? 0,
            'currency'         => $invoice->currency ?? 'XOF',
            'allocatable_type' => 'client',
            'allocatable_id'   => $invoice->partner_id ?? 0,
            'source_module'    => 'Accounting',
            'source_type'      => 'invoice',
            'source_id'        => $invoiceId,
            'description'      => "Facture #{$invoice->number}",
        ]);

        return ['module' => 'Accounting', 'source_id' => $invoiceId, 'imported' => 1];
    }

    private function importFromHR(int $timesheetId, int $tenantId): array
    {
        $entries = DB::table('timesheet_entries')->where('timesheet_id', $timesheetId)->get();

        $imported = 0;
        foreach ($entries as $entry) {
            $hours    = $entry->hours ?? 0;
            $rate     = $entry->hourly_rate ?? 0;
            $amount   = $hours * $rate;

            if ($amount <= 0) {
                continue;
            }

            $this->recordCost([
                'tenant_id'        => $tenantId,
                'category_code'    => 'OPEX',
                'amount'           => $amount,
                'currency'         => 'XOF',
                'allocatable_type' => 'project',
                'allocatable_id'   => $entry->project_id ?? 0,
                'source_module'    => 'HR',
                'source_type'      => 'labor',
                'source_id'        => $timesheetId,
                'description'      => "Main-d'œuvre feuille de temps #{$timesheetId}",
                'cost_driver'      => 'per_hour',
                'units'            => $hours,
                'unit_cost'        => $rate,
            ]);
            $imported++;
        }

        return ['module' => 'HR', 'source_id' => $timesheetId, 'imported' => $imported];
    }

    private function importFromLogistics(int $shipmentId, int $tenantId): array
    {
        $shipment = DB::table('shipments')->where('id', $shipmentId)->first();
        if (! $shipment) {
            return ['imported' => 0, 'error' => 'Shipment not found'];
        }

        $imported = 0;

        // Freight → OPEX
        if (! empty($shipment->freight_cost)) {
            $this->recordCost([
                'tenant_id'        => $tenantId,
                'category_code'    => 'OPEX',
                'amount'           => (float) $shipment->freight_cost,
                'currency'         => $shipment->currency ?? 'XOF',
                'allocatable_type' => 'product',
                'allocatable_id'   => $shipment->product_id ?? 0,
                'source_module'    => 'Logistics',
                'source_type'      => 'freight',
                'source_id'        => $shipmentId,
                'description'      => "Fret expédition #{$shipmentId}",
            ]);
            $imported++;
        }

        // Financing cost → FINEX
        if (! empty($shipment->financing_cost)) {
            $this->recordCost([
                'tenant_id'        => $tenantId,
                'category_code'    => 'FINEX',
                'amount'           => (float) $shipment->financing_cost,
                'currency'         => $shipment->currency ?? 'XOF',
                'allocatable_type' => 'product',
                'allocatable_id'   => $shipment->product_id ?? 0,
                'source_module'    => 'Logistics',
                'source_type'      => 'import_financing',
                'source_id'        => $shipmentId,
                'description'      => "Financement import expédition #{$shipmentId}",
            ]);
            $imported++;
        }

        return ['module' => 'Logistics', 'source_id' => $shipmentId, 'imported' => $imported];
    }

    private function importFromManufacturing(int $productionOrderId, int $tenantId): array
    {
        $depreciation = DB::table('acc_asset_depreciation')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();

        $imported = 0;
        foreach ($depreciation as $dep) {
            $this->recordCost([
                'tenant_id'        => $tenantId,
                'category_code'    => 'CAPEX',
                'amount'           => (float) ($dep->depreciation_amount ?? 0),
                'currency'         => 'XOF',
                'allocatable_type' => 'production_order',
                'allocatable_id'   => $productionOrderId,
                'source_module'    => 'Manufacturing',
                'source_type'      => 'depreciation',
                'source_id'        => $dep->id,
                'description'      => "Amortissement actif #{$dep->fixed_asset_id} — OF#{$productionOrderId}",
                'cost_driver'      => 'fixed',
            ]);
            $imported++;
        }

        return ['module' => 'Manufacturing', 'source_id' => $productionOrderId, 'imported' => $imported];
    }

    // ─── Classification helpers ───────────────────────────────────────────

    private function classifyAchatLine(array $line): string
    {
        $desc = strtolower($line['description'] ?? $line['product_name'] ?? '');

        // Heuristic: equipment/tooling → CAPEX; services/consumables → OPEX
        $capexKeywords = ['machine', 'équipement', 'outillage', 'moule', 'actif', 'licen'];
        foreach ($capexKeywords as $kw) {
            if (str_contains($desc, $kw)) {
                return 'CAPEX';
            }
        }

        return 'OPEX';
    }

    private function staticCostAnalysisFallback(array $snapshot): string
    {
        $total  = $snapshot['total'] ?? 0;
        $finex  = $snapshot['finex'] ?? 0;
        $riskex = $snapshot['riskex'] ?? 0;

        $finexPct  = $total > 0 ? round(($finex  / $total) * 100, 1) : 0;
        $riskexPct = $total > 0 ? round(($riskex / $total) * 100, 1) : 0;

        $warnings = [];
        if ($finexPct > 12) {
            $warnings[] = "FINEX ({$finexPct}%) dépasse 12% — vérifier le Besoin en Fonds de Roulement.";
        }
        if ($riskexPct > 8) {
            $warnings[] = "RISKEX ({$riskexPct}%) dépasse 8% — réviser les procédures qualité.";
        }

        $warningText = $warnings
            ? implode(' ', $warnings)
            : 'Structure de coûts dans les limites acceptables.';

        return "Analyse des coûts — Période {$snapshot['period']}: "
            . "CAPEX {$snapshot['capex']} XOF | OPEX {$snapshot['opex']} XOF | "
            . "FINEX {$snapshot['finex']} XOF ({$finexPct}%) | "
            . "RISKEX {$snapshot['riskex']} XOF ({$riskexPct}%). "
            . $warningText
            . " Recommandations: (1) Comparez au benchmark sectoriel. "
            . "(2) Si FINEX élevé, refinancez les imports via Lettre de Crédit. "
            . "(3) Classifiez selon OHADA: CAPEX→Classe 2, OPEX→Classe 6, FINEX→Classe 67, RISKEX→Classe 69.";
    }
}
