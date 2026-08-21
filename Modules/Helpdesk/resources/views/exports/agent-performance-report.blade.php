<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a2e; }
  .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
  .brand { font-size: 20px; font-weight: bold; color: #2e5be8; }
  .meta { text-align: right; font-size: 11px; color: #555; }
  .section-title { font-size: 14px; font-weight: bold; margin: 20px 0 8px; color: #2e5be8; border-bottom: 2px solid #2e5be8; padding-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  thead th { background: #2e5be8; color: #fff; padding: 6px 10px; text-align: left; font-size: 10px; }
  thead th.num { text-align: right; }
  tbody tr:nth-child(even) { background: #f8faff; }
  tbody td { padding: 5px 10px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
  tbody td.num { text-align: right; }
  .kpi-row { display: flex; gap: 10px; margin-bottom: 14px; }
  .kpi-card { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; text-align: center; }
  .kpi-value { font-size: 18px; font-weight: bold; color: #2e5be8; }
  .kpi-label { font-size: 9px; color: #6b7280; text-transform: uppercase; margin-top: 2px; }
  .list-item { padding: 6px 10px; border-left: 3px solid #16a34a; margin-bottom: 4px; background: #f0fdf4; font-size: 11px; }
  .list-item.concern { border-left-color: #dc2626; background: #fef2f2; }
  .empty { color: #9ca3af; font-style: italic; font-size: 11px; padding: 8px 0; }
  .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
</style>
</head>
<body>

<div class="header">
  <div class="brand">Life MDG ERP — Helpdesk</div>
  <div class="meta">
    Rapport de performance agent<br>
    {{ $agentName }} (#{{ $agentId }})<br>
    Généré le {{ $generatedAt->format('d/m/Y H:i') }}
  </div>
</div>

<div class="section-title">Synthèse (30 derniers jours)</div>
<div class="kpi-row">
  <div class="kpi-card">
    <div class="kpi-value">{{ $summary['total_tickets'] }}</div>
    <div class="kpi-label">Tickets traités</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-value">{{ $summary['resolved_tickets'] }}</div>
    <div class="kpi-label">Tickets résolus</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-value">{{ number_format($summary['average_satisfaction_score'], 1) }}/5</div>
    <div class="kpi-label">Satisfaction moyenne</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-value">{{ number_format($summary['sla_compliance_rate'] * 100, 0) }}%</div>
    <div class="kpi-label">Conformité SLA</div>
  </div>
</div>

<div class="section-title">Métriques détaillées</div>
<table>
  <thead>
    <tr><th>Indicateur</th><th class="num">Valeur</th></tr>
  </thead>
  <tbody>
    @foreach ($metrics as $key => $value)
      @continue(is_array($value))
      <tr>
        <td>{{ ucwords(str_replace('_', ' ', $key)) }}</td>
        <td class="num">{{ is_numeric($value) ? (is_float($value) ? number_format($value, 2) : $value) : $value }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="section-title">Points forts</div>
@forelse ($highlights as $highlight)
  <div class="list-item">{{ $highlight }}</div>
@empty
  <div class="empty">Aucun point fort particulier identifié sur la période.</div>
@endforelse

<div class="section-title">Points d'attention</div>
@forelse ($concerns as $concern)
  <div class="list-item concern">{{ $concern }}</div>
@empty
  <div class="empty">Aucun point d'attention identifié sur la période.</div>
@endforelse

<div class="footer">Life MDG ERP — Rapport généré automatiquement, à usage de coaching et non de sanction isolée.</div>

</body>
</html>
