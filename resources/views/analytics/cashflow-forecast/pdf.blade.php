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
  .cards { display: flex; justify-content: space-between; margin-bottom: 24px; }
  .card { width: 23%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
  .card-label { font-size: 9px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
  .card-value { font-size: 14px; font-weight: bold; }
  .card-danger .card-value { color: #dc2626; }
  .narrative { background: #f8faff; border-left: 3px solid #2e5be8; padding: 10px 14px; margin-bottom: 20px; font-size: 11px; }
  .section-title { font-size: 13px; font-weight: bold; margin: 16px 0 8px; color: #2e5be8; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  thead th { background: #2e5be8; color: #fff; padding: 6px 10px; text-align: left; font-size: 10px; }
  thead th.num { text-align: right; }
  tbody tr:nth-child(even) { background: #f8faff; }
  tbody td { padding: 5px 10px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
  tbody td.num { text-align: right; }
  tbody td.negative { color: #dc2626; font-weight: bold; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
  .badge-critical { background: #fee2e2; color: #991b1b; }
  .badge-warning { background: #fef3c7; color: #92400e; }
  .badge-info { background: #dbeafe; color: #1e40af; }
  .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="company">{{ config('app.name') }}</div>
    <div style="font-size:11px;color:#6b7280;margin-top:4px">Prévision de trésorerie</div>
  </div>
  <div class="meta">
    <div style="font-size:16px;font-weight:bold;margin-bottom:4px">TRÉSORERIE</div>
    <div>Horizon : {{ $result['horizon_days'] }} jours</div>
    <div>Généré le {{ now()->format('d/m/Y H:i') }}</div>
  </div>
</div>

<div class="cards">
  <div class="card">
    <div class="card-label">Solde final estimé</div>
    <div class="card-value">{{ number_format($result['summary']['final_balance'], 0, ',', ' ') }} {{ $result['summary']['currency'] }}</div>
  </div>
  <div class="card">
    <div class="card-label">Total encaissements</div>
    <div class="card-value">{{ number_format($result['summary']['total_inflow'], 0, ',', ' ') }} {{ $result['summary']['currency'] }}</div>
  </div>
  <div class="card">
    <div class="card-label">Total décaissements</div>
    <div class="card-value">{{ number_format($result['summary']['total_outflow'], 0, ',', ' ') }} {{ $result['summary']['currency'] }}</div>
  </div>
  <div class="card {{ $result['summary']['has_deficit'] ? 'card-danger' : '' }}">
    <div class="card-label">Solde minimum</div>
    <div class="card-value">{{ number_format($result['summary']['min_balance'], 0, ',', ' ') }} {{ $result['summary']['currency'] }}</div>
  </div>
</div>

<div class="narrative">{{ $result['narrative'] }}</div>

@if(count($result['gaps']) > 0)
<div class="section-title">Périodes de déficit détectées</div>
<table>
  <thead>
    <tr><th>Du</th><th>Au</th><th class="num">Solde minimum</th><th>Sévérité</th></tr>
  </thead>
  <tbody>
    @foreach($result['gaps'] as $gap)
    <tr>
      <td>{{ $gap['start_date'] }}</td>
      <td>{{ $gap['end_date'] }}</td>
      <td class="num negative">{{ number_format($gap['min_balance'], 0, ',', ' ') }} {{ $result['summary']['currency'] }}</td>
      <td><span class="badge badge-{{ $gap['severity'] }}">{{ $gap['severity'] }}</span></td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

<div class="section-title">Projection journalière</div>
<table>
  <thead>
    <tr>
      <th>Date</th>
      <th class="num">Encaissement</th>
      <th class="num">Décaissement</th>
      <th class="num">Net</th>
      <th class="num">Solde cumulé</th>
    </tr>
  </thead>
  <tbody>
    @foreach($result['daily'] as $day)
    <tr>
      <td>{{ $day['date'] }}</td>
      <td class="num">{{ number_format($day['inflow'], 0, ',', ' ') }}</td>
      <td class="num">{{ number_format($day['outflow'], 0, ',', ' ') }}</td>
      <td class="num {{ $day['net'] < 0 ? 'negative' : '' }}">{{ number_format($day['net'], 0, ',', ' ') }}</td>
      <td class="num {{ $day['running_balance'] < 0 ? 'negative' : '' }}">{{ number_format($day['running_balance'], 0, ',', ' ') }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="footer">
  Généré par {{ config('app.name') }} &bull; {{ now()->format('d/m/Y H:i') }} &bull; Devise : {{ $result['summary']['currency'] }}
</div>

</body>
</html>
