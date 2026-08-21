<?php

declare(strict_types=1);

namespace Modules\Strategy\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\CostingSheet;
use Modules\Inventory\Models\ProductionOrder;

/**
 * Chantier 26 (volet E) — KPI sectoriels textile/EPI pour la direction.
 * Contrairement au reste de Strategy First (KPIRegistryService, ratios
 * génériques Accounting/CRM/HR/Inventory déjà en place), ces indicateurs
 * sont spécifiques au métier de confection/EPI de Life MDG et se calculent
 * en direct sur les données déjà réelles du volet A de la feuille de route
 * (Chantier 21 : `CostingSheet`/`CostingSheetLine`, `ProductionOrder`,
 * `SourcingBenchmark`, `ProductTemplate.family`) — jamais de chiffre
 * inventé, jamais stocké : si aucune donnée n'existe encore pour un
 * indicateur, il dégrade proprement vers `null`/un tableau vide plutôt
 * que d'inventer une valeur.
 *
 * Délibérément placé dans Strategy (le domicile déjà établi du concept
 * "cockpit KPI pour la direction") mais interrogeant directement les
 * tables Inventory/Achats — même précédent cross-module déjà établi par
 * FinanceReviewService (Accounting) interrogeant Sales.
 *
 * Note documentée : `CostingSheet`/`ProductionOrder` n'ont aucune colonne
 * de tenant/company (gap déjà confirmé et documenté au Chantier 19 pour
 * l'ensemble du module Inventory) — ce service hérite donc du même
 * périmètre non scopé par tenant que le reste d'Inventory, pas une
 * régression introduite ici.
 */
class TextileSectorKpiService
{
    /**
     * Marge moyenne sur coût de revient (%), globale et par famille de
     * produit (via ProductTemplate.family) — uniquement les fiches non
     * brouillon (un devis encore en cours de saisie n'a pas de prix de
     * vente suggéré fiable).
     *
     * @return array{overall: array{avg_margin_percent: float|null, sheet_count: int}, by_family: array<int, array{family: string, avg_margin_percent: float, sheet_count: int}>}
     */
    public function marginByFamily(): array
    {
        $sheets = CostingSheet::query()
            ->whereIn('status', ['quoted', 'approved', 'archived'])
            ->where('suggested_selling_price', '>', 0)
            ->with('productTemplate')
            ->get();

        if ($sheets->isEmpty()) {
            return ['overall' => ['avg_margin_percent' => null, 'sheet_count' => 0], 'by_family' => []];
        }

        $margins = $sheets->map(fn (CostingSheet $s) => $this->marginPercent($s));

        $byFamily = $sheets
            ->groupBy(fn (CostingSheet $s) => $s->productTemplate?->family ?? 'non_classe')
            ->map(function ($group, string $family) {
                $m = $group->map(fn (CostingSheet $s) => $this->marginPercent($s));

                return [
                    'family' => $family,
                    'avg_margin_percent' => round($m->avg(), 1),
                    'sheet_count' => $group->count(),
                ];
            })
            ->values()
            ->all();

        return [
            'overall' => [
                'avg_margin_percent' => round($margins->avg(), 1),
                'sheet_count' => $sheets->count(),
            ],
            'by_family' => $byFamily,
        ];
    }

    private function marginPercent(CostingSheet $sheet): float
    {
        $price = (float) $sheet->suggested_selling_price;
        $cost  = (float) $sheet->total_cost_price;

        return $price > 0 ? round(($price - $cost) / $price * 100, 2) : 0.0;
    }

    /**
     * Structure moyenne du coût de revient (%) — part matière, main
     * d'œuvre, accessoires (montage+finition), valeur ajoutée (print/
     * broderie), lavage, et frais fixes — un indicateur classique du
     * secteur confection ("part matière" en particulier).
     *
     * @return array{sheet_count: int, structure: array<string, float>}
     */
    public function costStructure(): array
    {
        $sheets = CostingSheet::query()
            ->whereIn('status', ['quoted', 'approved', 'archived'])
            ->where('total_cost_price', '>', 0)
            ->get();

        if ($sheets->isEmpty()) {
            return ['sheet_count' => 0, 'structure' => []];
        }

        $components = ['total_material_cost', 'total_assembly_cost', 'total_finishing_cost', 'total_value_added_cost', 'washing_cost', 'labor_cost', 'fixed_cost_coefficient'];
        $shares = array_fill_keys($components, []);

        foreach ($sheets as $sheet) {
            $total = (float) $sheet->total_cost_price;
            foreach ($components as $component) {
                $shares[$component][] = $total > 0 ? ((float) $sheet->{$component} / $total) * 100 : 0.0;
            }
        }

        $labels = [
            'total_material_cost' => 'Matière',
            'total_assembly_cost' => 'Accessoires de montage',
            'total_finishing_cost' => 'Accessoires de finition',
            'total_value_added_cost' => 'Valeur ajoutée (print/broderie)',
            'washing_cost' => 'Lavage',
            'labor_cost' => "Main-d'œuvre",
            'fixed_cost_coefficient' => 'Frais fixes',
        ];

        $structure = [];
        foreach ($components as $component) {
            $values = $shares[$component];
            $structure[$labels[$component]] = round(array_sum($values) / count($values), 1);
        }

        return ['sheet_count' => $sheets->count(), 'structure' => $structure];
    }

    /**
     * Délai moyen de sous-traitance et taux de respect des délais —
     * calculés uniquement sur les commandes de production réellement
     * livrées (`status='delivered'`), globalement et par sous-traitant.
     *
     * @return array{overall: array{avg_lead_time_days: float|null, on_time_percent: float|null, delivered_count: int}, by_subcontractor: array<int, array{subcontractor: string, avg_lead_time_days: float, on_time_percent: float, delivered_count: int}>}
     */
    public function subcontractingLeadTime(): array
    {
        $delivered = ProductionOrder::query()
            ->where('status', 'delivered')
            ->whereNotNull('started_at')
            ->whereNotNull('delivered_at')
            ->with('subcontractor')
            ->get();

        if ($delivered->isEmpty()) {
            return ['overall' => ['avg_lead_time_days' => null, 'on_time_percent' => null, 'delivered_count' => 0], 'by_subcontractor' => []];
        }

        $leadTimes = $delivered->map(fn (ProductionOrder $o) => $o->started_at->diffInDays($o->delivered_at));
        $onTime    = $delivered->filter(fn (ProductionOrder $o) => $o->expected_delivery_at === null || $o->delivered_at->lessThanOrEqualTo($o->expected_delivery_at));

        $bySubcontractor = $delivered
            ->groupBy(fn (ProductionOrder $o) => $o->subcontractor?->name ?? 'Sans sous-traitant')
            ->map(function ($group, string $name) {
                $lt = $group->map(fn (ProductionOrder $o) => $o->started_at->diffInDays($o->delivered_at));
                $ot = $group->filter(fn (ProductionOrder $o) => $o->expected_delivery_at === null || $o->delivered_at->lessThanOrEqualTo($o->expected_delivery_at));

                return [
                    'subcontractor' => $name,
                    'avg_lead_time_days' => round($lt->avg(), 1),
                    'on_time_percent' => round($ot->count() / $group->count() * 100, 1),
                    'delivered_count' => $group->count(),
                ];
            })
            ->values()
            ->all();

        return [
            'overall' => [
                'avg_lead_time_days' => round($leadTimes->avg(), 1),
                'on_time_percent' => round($onTime->count() / $delivered->count() * 100, 1),
                'delivered_count' => $delivered->count(),
            ],
            'by_subcontractor' => $bySubcontractor,
        ];
    }

    /**
     * Répartition de la production (nombre de commandes + quantité) par
     * famille de produit (t-shirt/sweatshirt/chemise/... vs vêtements
     * semi-finis, etc.) — donne à la direction une vue de ce qui est
     * réellement en cours de fabrication, toutes commandes de production
     * non annulées confondues.
     *
     * @return array<int, array{family: string, order_count: int, total_quantity: int}>
     */
    public function productionMixByFamily(): array
    {
        $orders = ProductionOrder::query()
            ->where('status', '!=', 'cancelled')
            ->with('costingSheet.productTemplate')
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        return $orders
            ->groupBy(fn (ProductionOrder $o) => $o->costingSheet?->productTemplate?->family ?? 'non_classe')
            ->map(fn ($group, string $family) => [
                'family' => $family,
                'order_count' => $group->count(),
                'total_quantity' => (int) $group->sum('quantity'),
            ])
            ->values()
            ->all();
    }

    /**
     * Écart moyen entre le prix matière chiffré (CostingSheetLine.unit_price,
     * section "matière", converti dans la devise de l'observation) et le
     * prix matière réellement observé (SourcingBenchmark, Chantier 17) pour
     * le même ProductTemplate — un indicateur direct de la volatilité des
     * coûts d'approvisionnement déjà identifiée comme un besoin métier au
     * Chantier 17. N'agrège que les couples chiffrage/observation qui
     * partagent réellement un product_template_id — jamais une estimation
     * approximative.
     *
     * @return array{compared_count: int, avg_variance_percent: float|null}
     */
    public function materialPriceVariance(): array
    {
        $rows = DB::table('inventory_costing_sheet_lines as csl')
            ->join('inventory_sourcing_benchmarks as sb', 'sb.product_template_id', '=', 'csl.product_template_id')
            ->where('csl.section', 'matiere')
            ->whereNotNull('csl.product_template_id')
            ->whereColumn('csl.currency', 'sb.currency')
            ->select('csl.unit_price as quoted_price', 'sb.unit_price as observed_price')
            ->get();

        if ($rows->isEmpty()) {
            return ['compared_count' => 0, 'avg_variance_percent' => null];
        }

        $variances = $rows
            ->filter(fn ($r) => (float) $r->quoted_price > 0)
            ->map(fn ($r) => (((float) $r->observed_price - (float) $r->quoted_price) / (float) $r->quoted_price) * 100);

        if ($variances->isEmpty()) {
            return ['compared_count' => 0, 'avg_variance_percent' => null];
        }

        return [
            'compared_count' => $variances->count(),
            'avg_variance_percent' => round($variances->avg(), 1),
        ];
    }
}
