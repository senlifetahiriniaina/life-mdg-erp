<?php

namespace Modules\Strategy\Services;

use Modules\Strategy\Models\StrategySignal;
use Modules\Strategy\Models\StrategyKpiValue;
use Modules\Strategy\Models\StrategyKpi;

class SignalEngineService
{
    public function analyze(string $tenantId): array
    {
        $signals = [];

        $signals = array_merge($signals, $this->detectRevenueSignals($tenantId));
        $signals = array_merge($signals, $this->detectCrmSignals($tenantId));
        $signals = array_merge($signals, $this->detectHrSignals($tenantId));

        return $signals;
    }

    private function detectRevenueSignals(string $tenantId): array
    {
        $signals = [];

        // Check revenue KPIs for this tenant
        $revenueKpi = StrategyKpi::forTenant($tenantId)
            ->where('source_module', 'Accounting')
            ->where('source_key', 'monthly_revenue')
            ->first();

        if ($revenueKpi) {
            $values = StrategyKpiValue::where('kpi_id', $revenueKpi->id)
                ->orderBy('recorded_at', 'desc')
                ->limit(3)
                ->pluck('value')
                ->toArray();

            // If declining 2+ consecutive months → warning
            if (count($values) >= 3) {
                if ($values[0] < $values[1] && $values[1] < $values[2]) {
                    $signals[] = [
                        'type'          => 'warning',
                        'source_module' => 'Accounting',
                        'source_metric' => 'monthly_revenue',
                        'title'         => 'Baisse du chiffre d\'affaires sur 3 mois',
                        'description'   => 'Le CA mensuel est en baisse depuis 3 mois consécutifs.',
                        'recommendation' => 'Analyser les causes : perte de clients, baisse des ventes, saisonnalité.',
                    ];
                }
            }
        }

        // Check cash position vs expenses
        $cashKpi    = StrategyKpi::forTenant($tenantId)->where('source_key', 'cash_position')->first();
        $expenseKpi = StrategyKpi::forTenant($tenantId)->where('source_key', 'monthly_expenses')->first();

        if ($cashKpi && $expenseKpi) {
            $cashValue    = optional($cashKpi->latest)->value ?? 0;
            $expenseValue = optional($expenseKpi->latest)->value ?? 1;

            $runwayDays = ($expenseValue > 0) ? ($cashValue / $expenseValue) * 30 : 999;

            if ($runwayDays < 60) {
                $signals[] = [
                    'type'          => 'critical',
                    'source_module' => 'Accounting',
                    'source_metric' => 'cash_position',
                    'title'         => 'Trésorerie critique — moins de 60 jours de runway',
                    'description'   => sprintf('Runway estimé : %.0f jours.', $runwayDays),
                    'recommendation' => 'Accélérer les encaissements, négocier les délais fournisseurs.',
                ];
            }
        }

        return $signals;
    }

    private function detectCrmSignals(string $tenantId): array
    {
        $signals = [];

        $pipelineKpi = StrategyKpi::forTenant($tenantId)
            ->where('source_module', 'CRM')
            ->where('source_key', 'pipeline_value')
            ->first();

        if ($pipelineKpi) {
            $values = StrategyKpiValue::where('kpi_id', $pipelineKpi->id)
                ->orderBy('recorded_at', 'desc')
                ->limit(2)
                ->pluck('value')
                ->toArray();

            if (count($values) >= 2 && $values[1] > 0) {
                $change = (($values[0] - $values[1]) / $values[1]) * 100;

                if ($change < -20) {
                    $signals[] = [
                        'type'          => 'warning',
                        'source_module' => 'CRM',
                        'source_metric' => 'pipeline_value',
                        'title'         => 'Pipeline CRM en forte baisse',
                        'description'   => sprintf('Valeur du pipeline en baisse de %.1f%% par rapport au mois dernier.', abs($change)),
                        'recommendation' => 'Qualifier de nouvelles opportunités, renforcer la prospection.',
                    ];
                }
            }
        }

        return $signals;
    }

    private function detectHrSignals(string $tenantId): array
    {
        $signals = [];

        $headcountKpi = StrategyKpi::forTenant($tenantId)
            ->where('source_module', 'HR')
            ->where('source_key', 'headcount')
            ->first();

        $openPositionsKpi = StrategyKpi::forTenant($tenantId)
            ->where('source_module', 'HR')
            ->where('source_key', 'open_positions')
            ->first();

        if ($headcountKpi && $openPositionsKpi) {
            $headcount    = optional($headcountKpi->latest)->value ?? 0;
            $openPosition = optional($openPositionsKpi->latest)->value ?? 0;

            if ($headcount > 0 && ($openPosition / $headcount) > 0.20) {
                $signals[] = [
                    'type'          => 'warning',
                    'source_module' => 'HR',
                    'source_metric' => 'open_positions',
                    'title'         => 'Taux de postes vacants élevé',
                    'description'   => sprintf('%.0f postes ouverts pour %.0f employés (%.1f%%).', $openPosition, $headcount, ($openPosition / $headcount) * 100),
                    'recommendation' => 'Accélérer le recrutement ou redistribuer les responsabilités.',
                ];
            }
        }

        return $signals;
    }

    /**
     * Run analysis, persist new signals, dismiss old duplicates.
     */
    public function refreshSignals(string $tenantId): void
    {
        $signals = $this->analyze($tenantId);

        foreach ($signals as $signal) {
            // Avoid duplicates: check if similar unread signal exists
            $exists = StrategySignal::forTenant($tenantId)
                ->active()
                ->where('source_module', $signal['source_module'])
                ->where('source_metric', $signal['source_metric'])
                ->where('is_read', false)
                ->exists();

            if (!$exists) {
                StrategySignal::create(array_merge($signal, [
                    'tenant_id'   => $tenantId,
                    'detected_at' => now(),
                ]));
            }
        }
    }
}
