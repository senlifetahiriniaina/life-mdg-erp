<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiAnomalyDetectionService
{
    private const CACHE_TTL = 300;
    private const CACHE_PREFIX = 'ai_anomaly:';
    private const DISMISSED_PREFIX = 'ai_anomaly_dismissed:';

    private string $apiKey;
    private string $model;
    private bool $aiEnabled;

    public function __construct()
    {
        $this->apiKey    = config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''));
        $this->model     = config('services.anthropic.model', 'claude-sonnet-4-6');
        $this->aiEnabled = $this->apiKey !== '';
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Analyse a set of metrics for anomalies and return detected anomalies.
     *
     * @param  string $module
     * @param  array  $metrics   Key/value metrics to analyse
     * @param  int    $tenantId
     * @return array<int, array<string, mixed>>
     */
    public function detectAnomalies(string $module, array $metrics, int $tenantId): array
    {
        if ($this->aiEnabled) {
            return $this->detectWithClaude($module, $metrics, $tenantId);
        }

        return $this->detectWithRules($module, $metrics, $tenantId);
    }

    /**
     * Check inventory for low-stock anomalies (rule-based + optional AI triage).
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkInventoryAnomalies(int $tenantId): array
    {
        $anomalies = [];

        // Rule-based: products below reorder_point.
        // Real table is `inventory_products` (not bare `products`), and stock
        // quantity lives on the separate `inventory_stock` table (per-warehouse
        // rows), not a `stock_quantity` column on the product itself — this join
        // sums quantity across all warehouses.
        //
        // Chantier 19 Lot 3: this method's own prior comment claimed
        // `inventory_products.tenant_id` was "a real, populated column (see
        // ProductController::store())" — false, confirmed empirically (every
        // real row has it NULL). `ProductController::store()` actually sets it
        // from `auth()->user()->tenant_id`, this app's well-documented phantom
        // column (real DB column, never in `User::$fillable`, never populated
        // by the real registration flow). The result: this filter compared a
        // real int `$tenantId` (`company_id`) against a column that's always
        // NULL, so `checkInventoryAnomalies()` silently returned zero rows for
        // every tenant regardless of real low-stock data — confirmed by
        // creating a genuinely low-stock product the same way the real write
        // path does (tenant_id null) and observing 0 anomalies returned.
        // `inventory_products` has no `company_id` column at all (only the
        // dead `tenant_id`), so there is no real per-tenant column to filter on
        // here today — dropped the filter entirely, matching the identical,
        // already-documented precedent set by the accounting/HR checks below
        // in this same file (both explicitly don't filter by tenant for the
        // same reason) rather than leaving this one check silently dead.
        try {
            /** @var \Illuminate\Database\Eloquent\Collection $products */
            $products = \DB::table('inventory_products')
                ->join('inventory_stock', 'inventory_stock.product_id', '=', 'inventory_products.id')
                ->where('inventory_products.is_active', true)
                ->groupBy('inventory_products.id', 'inventory_products.name', 'inventory_products.reorder_point')
                ->havingRaw('SUM(inventory_stock.quantity) <= inventory_products.reorder_point')
                ->select([
                    'inventory_products.id',
                    'inventory_products.name',
                    \DB::raw('SUM(inventory_stock.quantity) as stock_quantity'),
                    'inventory_products.reorder_point',
                ])
                ->get();

            foreach ($products as $product) {
                $qty      = (int) $product->stock_quantity;
                $severity = $qty === 0 ? 'critical' : 'warning';

                $anomalies[] = $this->makeAnomaly(
                    module:     'Inventory',
                    type:       'low_stock',
                    severity:   $severity,
                    title:      ($qty === 0 ? 'Rupture de stock: ' : 'Stock critique: ') . $product->name,
                    description: sprintf(
                        'Le produit "%s" a un stock de %d unité(s) (seuil: %d).',
                        $product->name,
                        $qty,
                        (int) $product->reorder_point
                    ),
                    entityId:   (int) $product->id,
                    entityType: 'product',
                    aiPowered:  false,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AiAnomalyDetectionService: inventory check failed', ['error' => $e->getMessage()]);

            // Return a demo anomaly when table doesn't exist (dev/test)
            $anomalies[] = $this->makeAnomaly(
                module:     'Inventory',
                type:       'low_stock',
                severity:   'warning',
                title:      'Stock critique: Produit exemple',
                description: 'Exemple de détection de stock critique (règle de base).',
                entityId:   null,
                entityType: 'product',
                aiPowered:  false,
            );
        }

        return $this->filterDismissed($anomalies, $tenantId);
    }

    /**
     * Check accounting for unusual transactions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkAccountingAnomalies(int $tenantId): array
    {
        $anomalies = [];

        // Rule-based: invoices overdue > 30 days.
        // Real table is `acc_invoices` (not bare `invoices`); its amount column
        // is `total` (not `amount_total`), and it has no `unpaid` status value —
        // real statuses are draft|posted|paid|cancelled (see
        // `Modules\Accounting\Models\Invoice::scopeOverdue()`/`scopeUnpaid()`,
        // which use the same `status != paid/cancelled` + `due_date` check below).
        // `acc_invoices` has no tenant_id/company_id column at all in this app's
        // shared-DB schema, so this check is necessarily company-wide until
        // Accounting adds tenant scoping to that table — a documented gap here,
        // not something this AI-module fix silently papers over.
        try {
            $overdue = \DB::table('acc_invoices')
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->where('due_date', '<', now()->subDays(30)->toDateString())
                ->select(['id', 'number', 'total', 'due_date'])
                ->limit(10)
                ->get();

            foreach ($overdue as $invoice) {
                $anomalies[] = $this->makeAnomaly(
                    module:     'Accounting',
                    type:       'overdue_invoice',
                    severity:   'warning',
                    title:      'Facture en retard: ' . $invoice->number,
                    description: sprintf(
                        'La facture %s d\'un montant de %.2f est impayée depuis plus de 30 jours.',
                        $invoice->number,
                        (float) $invoice->total
                    ),
                    entityId:   (int) $invoice->id,
                    entityType: 'invoice',
                    aiPowered:  false,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AiAnomalyDetectionService: accounting check failed', ['error' => $e->getMessage()]);

            $anomalies[] = $this->makeAnomaly(
                module:     'Accounting',
                type:       'unusual_transaction',
                severity:   'warning',
                title:      'Transaction inhabituelle détectée',
                description: 'Une transaction d\'un montant anormalement élevé a été détectée.',
                entityId:   null,
                entityType: 'journal_entry',
                aiPowered:  false,
            );
        }

        return $this->filterDismissed($anomalies, $tenantId);
    }

    /**
     * Check HR for payroll anomalies (spikes, missing payslips).
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkHrAnomalies(int $tenantId): array
    {
        $anomalies = [];

        // Rule-based: employees without payslip this month.
        // Real tables are `hr_employees` (not bare `employees`) and `payslips`
        // (not bare `payslips` was already right, but its `period` column stores
        // a full date — the first day of the pay period, see
        // `PayrollIntegrationService::generatePayslip()` — not a 'Y-m' string,
        // so this now matches on a month date-range instead of string equality.
        // `hr_employees` has no `is_active` boolean (real column is the string
        // `status`, default 'active') and its `full_name` column is never
        // populated by real writes (computed on the fly by
        // `Employee::getFullName()`), so the name is built from first/last name
        // here instead. `hr_employees.tenant_id` is a documented phantom column
        // (see CLAUDE.md's Chantier 8.3 Payroll notes — no real write path
        // populates it, `EmployeeController::index()` itself has no tenant
        // filter) — this check intentionally doesn't filter by it either, for
        // the same reason and matching that same precedent, rather than
        // silently returning zero rows forever.
        try {
            $periodStart = now()->startOfMonth()->toDateString();
            $periodEnd   = now()->endOfMonth()->toDateString();

            $missing = \DB::table('hr_employees')
                ->where('hr_employees.status', 'active')
                ->whereNotExists(function ($query) use ($periodStart, $periodEnd) {
                    $query->select(\DB::raw(1))
                        ->from('payslips')
                        ->whereColumn('payslips.employee_id', 'hr_employees.id')
                        ->whereBetween('payslips.period', [$periodStart, $periodEnd]);
                })
                ->select(['hr_employees.id', 'hr_employees.first_name', 'hr_employees.last_name'])
                ->limit(10)
                ->get();

            foreach ($missing as $employee) {
                $fullName = trim($employee->first_name . ' ' . $employee->last_name);

                $anomalies[] = $this->makeAnomaly(
                    module:     'HR',
                    type:       'missing_payslip',
                    severity:   'warning',
                    title:      'Bulletin manquant: ' . $fullName,
                    description: sprintf(
                        'L\'employé "%s" n\'a pas de bulletin de salaire pour la période %s.',
                        $fullName,
                        now()->format('Y-m')
                    ),
                    entityId:   (int) $employee->id,
                    entityType: 'employee',
                    aiPowered:  false,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('AiAnomalyDetectionService: HR check failed', ['error' => $e->getMessage()]);

            $anomalies[] = $this->makeAnomaly(
                module:     'HR',
                type:       'missing_payslip',
                severity:   'warning',
                title:      'Bulletin de paie manquant (exemple)',
                description: 'Un ou plusieurs employés n\'ont pas de bulletin pour le mois en cours.',
                entityId:   null,
                entityType: 'employee',
                aiPowered:  false,
            );
        }

        return $this->filterDismissed($anomalies, $tenantId);
    }

    /**
     * Get all active (non-dismissed) anomalies for a tenant, across all modules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveAnomalies(int $tenantId): array
    {
        $cacheKey = self::CACHE_PREFIX . 'all:' . $tenantId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId) {
            $all = array_merge(
                $this->checkInventoryAnomalies($tenantId),
                $this->checkAccountingAnomalies($tenantId),
                $this->checkHrAnomalies($tenantId),
            );

            // Sort: critical first, then warning, then info
            usort($all, static function (array $a, array $b) {
                $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
                return ($order[$a['severity']] ?? 9) <=> ($order[$b['severity']] ?? 9);
            });

            return $all;
        });
    }

    /**
     * Mark an anomaly as dismissed (will be excluded from active list).
     *
     * Also busts the per-tenant `getActiveAnomalies()` aggregate cache (when
     * $tenantId is known) — otherwise a dismissed anomaly kept reappearing in
     * the active list for up to CACHE_TTL seconds after being dismissed, since
     * only the per-anomaly dismissed flag was ever being written.
     */
    public function dismissAnomaly(string $anomalyId, ?int $tenantId = null): void
    {
        $key = self::DISMISSED_PREFIX . $anomalyId;
        Cache::put($key, true, now()->addDays(7));

        if ($tenantId !== null) {
            Cache::forget(self::CACHE_PREFIX . 'all:' . $tenantId);
        }
    }

    // -------------------------------------------------------------------------
    // Private — Claude AI detection
    // -------------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function detectWithClaude(string $module, array $metrics, int $tenantId): array
    {
        $systemPrompt = <<<PROMPT
You are WideHalo's anomaly detection engine.
You receive a JSON object with a module name and a set of business metrics.
Analyse the metrics and return a JSON array of anomalies.
Each anomaly must have:
  { "type": string, "severity": "critical|warning|info", "title": string, "description": string }
Return an empty array [] if no anomaly is found.
Never return markdown or explanations — only the JSON array.
Keep titles under 60 characters. Write in French.
PROMPT;

        $userMessage = json_encode(['module' => $module, 'metrics' => $metrics]);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 400,
                'system'     => [
                    ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                ],
                'messages' => [['role' => 'user', 'content' => $userMessage]],
            ]);

            if (!$response->successful()) {
                return $this->detectWithRules($module, $metrics, $tenantId);
            }

            $text    = $response->json('content.0.text', '[]');
            $text    = preg_replace('/^```json\s*/m', '', $text ?? '[]');
            $text    = preg_replace('/^```\s*/m', '', $text ?? '[]');
            $decoded = json_decode(trim($text), true);

            if (!is_array($decoded)) {
                return $this->detectWithRules($module, $metrics, $tenantId);
            }

            $result = [];
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $result[] = $this->makeAnomaly(
                    module:     $module,
                    type:       (string) ($item['type'] ?? 'general'),
                    severity:   in_array($item['severity'] ?? '', ['critical', 'warning', 'info'], true) ? $item['severity'] : 'info',
                    title:      (string) ($item['title'] ?? 'Anomalie détectée'),
                    description: (string) ($item['description'] ?? ''),
                    entityId:   isset($item['entity_id']) ? (int) $item['entity_id'] : null,
                    entityType: isset($item['entity_type']) ? (string) $item['entity_type'] : null,
                    aiPowered:  true,
                );
            }

            return $this->filterDismissed($result, $tenantId);
        } catch (\Throwable $e) {
            Log::warning('AiAnomalyDetectionService: Claude call failed', ['error' => $e->getMessage()]);
            return $this->detectWithRules($module, $metrics, $tenantId);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function detectWithRules(string $module, array $metrics, int $tenantId): array
    {
        $anomalies = [];

        // Generic rule-based checks on common metric patterns
        foreach ($metrics as $key => $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $numericValue = (float) $value;

            // Detect zero-stock metrics
            if (str_contains(strtolower((string) $key), 'stock') && $numericValue === 0.0) {
                $anomalies[] = $this->makeAnomaly(
                    module:     $module,
                    type:       'low_stock',
                    severity:   'critical',
                    title:      'Stock nul détecté: ' . $key,
                    description: "Le métrique \"{$key}\" indique un stock à zéro.",
                    entityId:   null,
                    entityType: null,
                    aiPowered:  false,
                );
            }

            // Detect overdue counts
            if (str_contains(strtolower((string) $key), 'overdue') && $numericValue > 0) {
                $anomalies[] = $this->makeAnomaly(
                    module:     $module,
                    type:       'overdue_invoice',
                    severity:   'warning',
                    title:      'Éléments en retard: ' . $key,
                    description: "Le métrique \"{$key}\" signale {$numericValue} éléments en retard.",
                    entityId:   null,
                    entityType: null,
                    aiPowered:  false,
                );
            }
        }

        return $this->filterDismissed($anomalies, $tenantId);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function makeAnomaly(
        string  $module,
        string  $type,
        string  $severity,
        string  $title,
        string  $description,
        ?int    $entityId,
        ?string $entityType,
        bool    $aiPowered,
    ): array {
        $id = md5($module . ':' . $type . ':' . ($entityId ?? $title));

        return [
            'id'          => $id,
            'module'      => $module,
            'type'        => $type,
            'severity'    => $severity,
            'title'       => $title,
            'description' => $description,
            'entity_id'   => $entityId,
            'entity_type' => $entityType,
            'detected_at' => now()->toIso8601String(),
            'ai_powered'  => $aiPowered,
        ];
    }

    /**
     * Remove dismissed anomalies from the list.
     *
     * @param  array<int, array<string, mixed>> $anomalies
     * @return array<int, array<string, mixed>>
     */
    private function filterDismissed(array $anomalies, int $tenantId): array
    {
        return array_values(array_filter($anomalies, function (array $anomaly): bool {
            $key = self::DISMISSED_PREFIX . $anomaly['id'];
            return !Cache::has($key);
        }));
    }
}
