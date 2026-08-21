<?php

declare(strict_types=1);

namespace Modules\Sales\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\SalesObjective;
use Modules\Sales\Models\SalesOrder;

/**
 * Chantier 26 (volet B) — objectifs commerciaux assistés par IA. Calcule 2-3
 * propositions de cible de chiffre d'affaires à partir de l'historique réel
 * (jamais inventées) pour un périmètre donné (équipe/commercial/client/
 * catégorie de produits), puis laisse l'utilisateur les modifier et en
 * valider une seule.
 */
class SalesObjectiveService
{
    private const LOOKBACK_MONTHS  = 6;
    private const GROWTH_MODERATE  = 5.0;
    private const GROWTH_AMBITIOUS = 15.0;

    public function __construct(private readonly SalesObjectiveAiService $ai) {}

    /**
     * Génère et persiste 3 propositions (conservateur/modéré/ambitieux)
     * pour le périmètre et la période donnés — status='proposed'.
     */
    public function proposeObjectives(
        string $scope,
        ?int $scopeRefId,
        int $tenantId,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $createdBy = null,
    ): Collection {
        $lookbackFrom = $periodStart->copy()->subMonths(self::LOOKBACK_MONTHS)->startOfDay();
        $lookbackTo   = $periodStart->copy()->subDay()->endOfDay();

        $monthly = $this->historicalMonthlyTotals($scope, $scopeRefId, $tenantId, $lookbackFrom, $lookbackTo);
        $average = count($monthly) > 0 ? array_sum($monthly) / count($monthly) : 0.0;

        // Nombre de mois couverts par la période cible (arrondi au mois
        // entier supérieur) — un objectif trimestriel doit viser ~3x la
        // moyenne mensuelle, pas la moyenne mensuelle brute.
        $periodMonths = max(1, (int) ceil($periodStart->diffInDays($periodEnd) / 30));

        $candidates = [
            ['label' => 'Conservateur', 'growth_rate_percent' => 0.0],
            ['label' => 'Modéré', 'growth_rate_percent' => self::GROWTH_MODERATE],
            ['label' => 'Ambitieux', 'growth_rate_percent' => self::GROWTH_AMBITIOUS],
        ];

        foreach ($candidates as &$c) {
            $c['target_amount'] = round($average * (1 + $c['growth_rate_percent'] / 100) * $periodMonths, 2);
        }
        unset($c);

        $currency    = $this->tenantCurrency($tenantId);
        $scopeLabel  = $this->scopeLabel($scope, $scopeRefId);
        $periodLabel = $periodStart->format('d/m/Y').' – '.$periodEnd->format('d/m/Y');

        $basisTexts = $this->ai->generateBasisTexts($scopeLabel, $candidates, $average, $currency, $periodLabel);

        $created = collect();
        foreach ($candidates as $i => $c) {
            $created->push(SalesObjective::create([
                'tenant_id'           => $tenantId,
                'scope'               => $scope,
                'scope_ref_id'        => $scopeRefId,
                'period_start'        => $periodStart->toDateString(),
                'period_end'          => $periodEnd->toDateString(),
                'target_amount'       => $c['target_amount'],
                'currency'            => $currency,
                'proposal_label'      => $c['label'],
                'basis'               => $basisTexts[$i] ?? null,
                'growth_rate_percent' => $c['growth_rate_percent'],
                'status'              => 'proposed',
                'source'              => 'ai',
                'created_by'          => $createdBy,
            ]));
        }

        return $created;
    }

    /**
     * Valide une proposition (avec éventuelles modifications de l'utilisateur
     * appliquées avant validation) et rejette automatiquement les autres
     * propositions du même (scope, scope_ref_id, période).
     */
    public function validate(SalesObjective $objective, int $userId, array $overrides = []): SalesObjective
    {
        if (! empty($overrides)) {
            $objective->fill(Arr::only($overrides, ['target_amount', 'period_start', 'period_end', 'notes']));
        }

        $objective->status       = 'validated';
        $objective->validated_by = $userId;
        $objective->validated_at = now();
        $objective->save();

        SalesObjective::siblings($objective)->where('status', 'proposed')->update(['status' => 'rejected']);

        return $objective;
    }

    /** @return array<string, float> ['2026-01' => total, ...] */
    private function historicalMonthlyTotals(string $scope, ?int $scopeRefId, int $tenantId, Carbon $from, Carbon $to): array
    {
        if ($scope === 'category') {
            $rows = DB::table('sales_order_lines as sol')
                ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
                ->join('inventory_products as p', 'p.id', '=', 'sol.product_id')
                ->where('so.tenant_id', $tenantId)
                ->whereNotIn('so.status', ['cancelled', 'returned'])
                ->whereBetween('so.confirmed_at', [$from, $to])
                ->when($scopeRefId, fn ($q) => $q->where('p.category_id', $scopeRefId))
                ->select('sol.line_total', 'so.confirmed_at')
                ->get()
                ->map(fn ($r) => ['total' => (float) $r->line_total, 'date' => $r->confirmed_at]);

            return $this->bucketByMonth($rows);
        }

        $query = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereBetween('confirmed_at', [$from, $to]);

        match ($scope) {
            'rep'    => $query->where('sales_rep_id', $scopeRefId),
            'client' => $query->where('contact_id', $scopeRefId),
            default  => null, // 'global' — pas de filtre supplémentaire
        };

        $rows = $query->get(['total', 'confirmed_at'])
            ->map(fn ($r) => ['total' => (float) $r->total, 'date' => $r->confirmed_at]);

        return $this->bucketByMonth($rows);
    }

    /**
     * Regroupe en PHP plutôt qu'en SQL — un GROUP BY sur une expression de
     * date portable entre SQLite (dev/test/CI) et MySQL (prod) est un piège
     * déjà documenté au Chantier 26 volet A (collapse d'alias SQLite) ;
     * les volumes ici (commandes d'un tenant sur quelques mois) rendent le
     * bucketing PHP sûr et plus simple qu'une expression SQL par driver.
     *
     * @param  Collection<int, array{total: float, date: mixed}>  $rows
     * @return array<string, float>
     */
    private function bucketByMonth(Collection $rows): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            if (! $row['date']) {
                continue;
            }
            $key = Carbon::parse($row['date'])->format('Y-m');
            $buckets[$key] = ($buckets[$key] ?? 0) + $row['total'];
        }

        return $buckets;
    }

    private function tenantCurrency(int $tenantId): string
    {
        return DB::table('companies')->where('id', $tenantId)->value('currency') ?? 'MGA';
    }

    private function scopeLabel(string $scope, ?int $scopeRefId): string
    {
        return match ($scope) {
            'global'   => "toute l'équipe commerciale",
            'rep'      => 'le commercial '.(DB::table('users')->where('id', $scopeRefId)->value('name') ?? "#{$scopeRefId}"),
            'client'   => 'le client '.$this->clientLabel($scopeRefId),
            'category' => 'la catégorie de produits '.(DB::table('inventory_categories')->where('id', $scopeRefId)->value('name') ?? "#{$scopeRefId}"),
            default    => 'périmètre inconnu',
        };
    }

    private function clientLabel(?int $scopeRefId): string
    {
        // crm_contacts a first_name/last_name, jamais de colonne `name` —
        // même piège déjà documenté dans ce dépôt (bug de mapping d'import).
        $contact = DB::table('crm_contacts')->where('id', $scopeRefId)->first(['first_name', 'last_name']);
        if ($contact) {
            return trim("{$contact->first_name} {$contact->last_name}") ?: "#{$scopeRefId}";
        }

        return "#{$scopeRefId}";
    }
}
