<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a2e; }
  .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
  .company { font-size: 22px; font-weight: bold; color: #2e5be8; }
  .meta { text-align: right; font-size: 11px; color: #555; }
  .section-title { font-size: 14px; font-weight: bold; margin: 20px 0 8px; color: #2e5be8; border-bottom: 2px solid #2e5be8; padding-bottom: 4px; }
  .subsection-title { font-size: 11px; font-weight: bold; margin: 10px 0 6px; color: #374151; text-transform: uppercase; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  thead th { background: #2e5be8; color: #fff; padding: 6px 10px; text-align: left; font-size: 10px; }
  thead th.num { text-align: right; }
  tbody tr:nth-child(even) { background: #f8faff; }
  tbody td { padding: 5px 10px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
  tbody td.num { text-align: right; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
  .badge-green { background: #dcfce7; color: #166534; }
  .badge-amber { background: #fef3c7; color: #92400e; }
  .badge-red { background: #fee2e2; color: #991b1b; }
  .health-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 6px; }
  .health-score { font-size: 16px; font-weight: bold; }
  .health-green { color: #16a34a; }
  .health-amber { color: #d97706; }
  .health-red { color: #dc2626; }
  .empty { color: #9ca3af; font-style: italic; font-size: 11px; padding: 8px 0; }
  .okr-obj { font-weight: bold; }
  .okr-kr { padding-left: 16px; color: #4b5563; }
  .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
  .page-break { page-break-before: always; }
</style>
</head>
<body>

@php
  $statusLabels = ['green' => 'Bon', 'amber' => 'À surveiller', 'red' => 'Critique'];
  $healthClass = function ($score) {
      if ($score === null) return '';
      if ($score >= 70) return 'health-green';
      if ($score >= 40) return 'health-amber';
      return 'health-red';
  };
  // Aplatit récursivement l'arbre OKR (objectif → key_results + children) —
  // même forme d'aplatissement que Sheets/OkrSheet.php (dupliquée plutôt
  // que partagée, précédent déjà établi dans ce module). Note : la clé est
  // bien `key_results` (snake_case) — Eloquent::relationsToArray() snake-case
  // le nom de la relation eager-loaded même si with(['keyResults']) l'appelle
  // en camelCase ; confirmé empiriquement via tinker avant d'écrire ce bloc.
  $flattenOkr = function (array $objectives) use (&$flattenOkr) {
      $rows = [];
      foreach ($objectives as $objective) {
          $rows[] = ['type' => 'objective', 'data' => $objective];
          foreach ($objective['key_results'] ?? [] as $kr) {
              $rows[] = ['type' => 'kr', 'data' => $kr];
          }
          if (! empty($objective['children'])) {
              $rows = array_merge($rows, $flattenOkr($objective['children']));
          }
      }
      return $rows;
  };
  $okrRows = $flattenOkr($okr['objectives'] ?? []);
@endphp

<div class="header">
  <div>
    <div class="company">{{ $company_name }}</div>
    <div style="font-size:11px;color:#6b7280;margin-top:4px">Rapport de pilotage stratégique</div>
  </div>
  <div class="meta">
    <div style="font-size:16px;font-weight:bold;margin-bottom:4px">DIRECTION &amp; PILOTAGE</div>
    <div>Période : {{ $period_label }}</div>
    <div>Généré le {{ $generated_at->format('d/m/Y H:i') }}</div>
  </div>
</div>

{{-- ── Ratios ─────────────────────────────────────────────────────────── --}}
<div class="section-title">Ratios KPI stratégiques</div>
@if(count($ratios) === 0)
  <p class="empty">Aucune donnée disponible.</p>
@else
  <table>
    <thead>
      <tr><th>Module</th><th>Ratio</th><th class="num">Valeur actuelle</th><th>Unité</th><th class="num">Référence secteur</th><th>Statut</th></tr>
    </thead>
    <tbody>
      @foreach($ratios as $ratio)
      <tr>
        <td>{{ $ratio['module'] ?? '' }}</td>
        <td>{{ $ratio['name'] ?? '' }}</td>
        <td class="num">{{ $ratio['current_value'] ?? '' }}</td>
        <td>{{ $ratio['unit'] ?? '' }}</td>
        <td class="num">{{ $ratio['benchmark_value'] ?? '—' }}</td>
        <td>
          @php $status = $ratio['status'] ?? ''; @endphp
          <span class="badge badge-{{ $status }}">{{ $statusLabels[$status] ?? $status }}</span>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
@endif

{{-- ── Plans stratégiques ─────────────────────────────────────────────── --}}
<div class="section-title">Santé des plans stratégiques</div>
@if($plans->isEmpty())
  <p class="empty">Aucune donnée disponible.</p>
@else
  @foreach($plans as $plan)
  <div class="health-row">
    <div>
      <strong>{{ $plan->name }}</strong> — {{ $plan->status }}
      <span style="color:#6b7280"> · {{ $plan->objectives_count ?? 0 }} objectif(s)</span>
    </div>
    <div class="health-score {{ $healthClass($plan->health_score) }}">{{ $plan->health_score ?? 0 }}%</div>
  </div>
  @endforeach
@endif

{{-- ── Corrélations ───────────────────────────────────────────────────── --}}
<div class="section-title">Corrélations clés entre indicateurs</div>
@if(count($correlations) === 0)
  <p class="empty">Aucune donnée disponible.</p>
@else
  <table>
    <thead>
      <tr><th>KPI A</th><th>KPI B</th><th class="num">Coefficient (r)</th><th>Interprétation</th></tr>
    </thead>
    <tbody>
      @foreach($correlations as $c)
      <tr>
        <td>{{ $c['kpi_a'] ?? '' }}</td>
        <td>{{ $c['kpi_b'] ?? '' }}</td>
        <td class="num">{{ number_format((float) ($c['coefficient'] ?? 0), 2) }}</td>
        <td>{{ $c['interpretation'] ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
@endif

<div class="page-break"></div>

{{-- ── OKR ────────────────────────────────────────────────────────────── --}}
<div class="section-title">Objectifs &amp; résultats clés (OKR){{ ($okr['plan_name'] ?? null) ? ' — '.$okr['plan_name'] : '' }}</div>
@if(count($okrRows) === 0)
  <p class="empty">Aucune donnée disponible.</p>
@else
  <table>
    <thead>
      <tr><th>Objectif / Résultat clé</th><th class="num">Progression</th><th>Statut / Détail</th></tr>
    </thead>
    <tbody>
      @foreach($okrRows as $row)
      @if($row['type'] === 'objective')
      <tr>
        <td class="okr-obj">{{ $row['data']['title'] ?? '' }}</td>
        <td class="num">{{ $row['data']['progress'] ?? 0 }}%</td>
        <td>{{ $row['data']['status'] ?? '' }}</td>
      </tr>
      @else
      <tr>
        <td class="okr-kr">↳ {{ $row['data']['title'] ?? '' }}</td>
        <td class="num">{{ $row['data']['progress'] ?? 0 }}%</td>
        <td>{{ $row['data']['current_value'] ?? '' }} / {{ $row['data']['target_value'] ?? '' }} {{ $row['data']['unit'] ?? '' }}</td>
      </tr>
      @endif
      @endforeach
    </tbody>
  </table>
@endif

{{-- ── KPI Sectoriels textile/EPI ────────────────────────────────────── --}}
<div class="section-title">KPI sectoriels textile / EPI</div>

<div class="subsection-title">Marge moyenne sur coût de revient</div>
@if(($sector['margin']['overall']['sheet_count'] ?? 0) === 0)
  <p class="empty">Aucune donnée disponible.</p>
@else
  <table>
    <thead><tr><th>Famille de produit</th><th class="num">Marge moyenne (%)</th><th class="num">Nb. fiches</th></tr></thead>
    <tbody>
      <tr><td><strong>Global</strong></td><td class="num">{{ $sector['margin']['overall']['avg_margin_percent'] }}%</td><td class="num">{{ $sector['margin']['overall']['sheet_count'] }}</td></tr>
      @foreach($sector['margin']['by_family'] as $f)
      <tr><td>{{ $f['family'] }}</td><td class="num">{{ $f['avg_margin_percent'] }}%</td><td class="num">{{ $f['sheet_count'] }}</td></tr>
      @endforeach
    </tbody>
  </table>
@endif

<div class="subsection-title">Structure du coût de revient</div>
@if(($sector['cost_structure']['sheet_count'] ?? 0) === 0)
  <p class="empty">Aucune donnée disponible.</p>
@else
  <table>
    <thead><tr><th>Composante</th><th class="num">Part (%)</th></tr></thead>
    <tbody>
      @foreach($sector['cost_structure']['structure'] as $label => $percent)
      <tr><td>{{ $label }}</td><td class="num">{{ $percent }}%</td></tr>
      @endforeach
    </tbody>
  </table>
@endif

<div class="subsection-title">Délai de sous-traitance</div>
@if(($sector['lead_time']['overall']['delivered_count'] ?? 0) === 0)
  <p class="empty">Aucune donnée disponible.</p>
@else
  <table>
    <thead><tr><th>Sous-traitant</th><th class="num">Délai moyen (jours)</th><th class="num">Taux de respect (%)</th></tr></thead>
    <tbody>
      <tr>
        <td><strong>Global</strong></td>
        <td class="num">{{ $sector['lead_time']['overall']['avg_lead_time_days'] }}</td>
        <td class="num">{{ $sector['lead_time']['overall']['on_time_percent'] }}%</td>
      </tr>
      @foreach($sector['lead_time']['by_subcontractor'] as $sc)
      <tr>
        <td>{{ $sc['subcontractor'] }}</td>
        <td class="num">{{ $sc['avg_lead_time_days'] }}</td>
        <td class="num">{{ $sc['on_time_percent'] }}%</td>
      </tr>
      @endforeach
    </tbody>
  </table>
@endif

<div class="subsection-title">Mix de production par famille</div>
@if(count($sector['production_mix']) === 0)
  <p class="empty">Aucune donnée disponible.</p>
@else
  <table>
    <thead><tr><th>Famille</th><th class="num">Nb. commandes</th><th class="num">Quantité totale</th></tr></thead>
    <tbody>
      @foreach($sector['production_mix'] as $mix)
      <tr><td>{{ $mix['family'] }}</td><td class="num">{{ $mix['order_count'] }}</td><td class="num">{{ $mix['total_quantity'] }}</td></tr>
      @endforeach
    </tbody>
  </table>
@endif

<div class="subsection-title">Écart prix matière chiffré vs observé</div>
@if(($sector['material_variance']['compared_count'] ?? 0) === 0)
  <p class="empty">Aucune donnée disponible.</p>
@else
  <p>Écart moyen : <strong>{{ $sector['material_variance']['avg_variance_percent'] }}%</strong> sur {{ $sector['material_variance']['compared_count'] }} comparaison(s).</p>
@endif

<div class="footer">
  Généré par {{ config('app.name') }} &bull; {{ $generated_at->format('d/m/Y H:i') }} &bull; Confidentiel — usage interne direction
</div>

</body>
</html>
