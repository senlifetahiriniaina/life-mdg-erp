<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a2e; }
  .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
  .company { font-size: 20px; font-weight: bold; color: #2e5be8; }
  .meta { text-align: right; font-size: 11px; color: #555; }
  .title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
  .summary { display: flex; gap: 16px; margin-bottom: 24px; }
  .summary-card { flex: 1; background: #f8faff; border-radius: 6px; padding: 10px 12px; }
  .summary-card .label { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; }
  .summary-card .value { font-size: 16px; font-weight: bold; color: #2e5be8; margin-top: 2px; }
  h2 { font-size: 13px; margin: 20px 0 8px; color: #374151; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  thead th { background: #2e5be8; color: #fff; padding: 6px 10px; text-align: left; font-size: 10px; }
  thead th.num { text-align: right; }
  tbody tr:nth-child(even) { background: #f8faff; }
  tbody td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
  tbody td.num { text-align: right; }
  .empty { color: #9ca3af; font-size: 11px; padding: 8px 0; }
  .footer { margin-top: 32px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="company">{{ config('app.name') }}</div>
    <div class="title" style="margin-top:8px">Rapport de facturation par projet</div>
  </div>
  <div class="meta">
    <div>Période : {{ $data['period']['from'] }} au {{ $data['period']['to'] }}</div>
    <div>Généré le {{ now()->format('Y-m-d H:i') }}</div>
    <div>Devise : XOF</div>
  </div>
</div>

<div class="summary">
  <div class="summary-card">
    <div class="label">Heures facturables</div>
    <div class="value">{{ number_format($data['total_billable_hours'], 2, ',', ' ') }} h</div>
  </div>
  <div class="summary-card">
    <div class="label">Montant facturable</div>
    <div class="value">{{ number_format($data['total_billable_amount'], 0, ',', ' ') }} XOF</div>
  </div>
  <div class="summary-card">
    <div class="label">Taux horaire moyen</div>
    <div class="value">{{ number_format($data['avg_hourly_rate'], 0, ',', ' ') }} XOF</div>
  </div>
</div>

<h2>Par projet</h2>
@if (count($data['by_project']) === 0)
  <p class="empty">Aucune heure facturable sur cette période.</p>
@else
  <table>
    <thead>
      <tr>
        <th>Projet</th>
        <th class="num">Heures facturables</th>
        <th class="num">Taux horaire moyen</th>
        <th class="num">Montant</th>
        <th class="num">Employés</th>
        <th class="num">Saisies</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($data['by_project'] as $row)
        <tr>
          <td>{{ $row['project_name'] ?? '—' }}</td>
          <td class="num">{{ number_format($row['billable_hours'], 2, ',', ' ') }}</td>
          <td class="num">{{ number_format($row['avg_hourly_rate'], 0, ',', ' ') }}</td>
          <td class="num">{{ number_format($row['billable_amount'], 0, ',', ' ') }}</td>
          <td class="num">{{ $row['employee_count'] }}</td>
          <td class="num">{{ $row['entry_count'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

<h2>Par projet et employé</h2>
@if (count($data['by_employee_project']) === 0)
  <p class="empty">Aucune heure facturable sur cette période.</p>
@else
  <table>
    <thead>
      <tr>
        <th>Projet</th>
        <th>Employé</th>
        <th class="num">Heures facturables</th>
        <th class="num">Taux horaire</th>
        <th class="num">Montant</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($data['by_employee_project'] as $row)
        <tr>
          <td>{{ $row['project_name'] ?? '—' }}</td>
          <td>{{ $row['employee_name'] ?? '—' }}</td>
          <td class="num">{{ number_format($row['billable_hours'], 2, ',', ' ') }}</td>
          <td class="num">{{ number_format($row['hourly_rate'], 0, ',', ' ') }}</td>
          <td class="num">{{ number_format($row['amount'], 0, ',', ' ') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

<div class="footer">
  {{ config('app.name') }} — Rapport de facturation par projet (Timesheets) — {{ now()->format('Y-m-d H:i') }}
</div>

</body>
</html>
