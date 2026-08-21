<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a2e; }
  .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
  .company { font-size: 22px; font-weight: bold; color: #2e5be8; }
  .subtitle { font-size: 11px; color: #6b7280; margin-top: 4px; }
  .meta { text-align: right; font-size: 11px; color: #555; }
  .meta .title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; margin-top: 6px; }
  .badge-ok { background: #dcfce7; color: #166534; }
  .badge-warn { background: #fee2e2; color: #991b1b; }
  .columns { display: flex; }
  .col { width: 50%; }
  .col + .col { padding-left: 12px; }
  .panel { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 12px; }
  .panel-head { padding: 8px 12px; font-weight: bold; background: #f8faff; border-bottom: 1px solid #e5e7eb; }
  .panel-head .amt { float: right; }
  .actif .panel-head { color: #2e5be8; }
  .passif .panel-head { color: #dc2626; }
  .section-head { padding: 6px 12px; font-weight: bold; font-size: 11px; background: #f3f4f6; }
  .section-head .amt { float: right; }
  table { width: 100%; border-collapse: collapse; }
  tbody td { padding: 4px 12px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
  tbody td.num { text-align: right; font-variant-numeric: tabular-nums; }
  .code { font-family: monospace; color: #6b7280; }
  .empty { padding: 6px 12px; font-size: 10px; color: #9ca3af; font-style: italic; }
  .totals { display: flex; justify-content: space-between; margin-top: 16px; padding: 12px; background: #f8faff; border-radius: 6px; font-weight: bold; }
  .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="company">{{ config('app.name') }}</div>
    <div class="subtitle">Bilan SYSCOHADA — Actif immobilisé / Stocks / Créances / Trésorerie</div>
  </div>
  <div class="meta">
    <div class="title">BILAN</div>
    <div>Période : {{ $data['period'] }}</div>
    <div>Généré le {{ now()->format('d/m/Y H:i') }}</div>
    <span class="badge {{ $data['equilibre'] ? 'badge-ok' : 'badge-warn' }}">
      {{ $data['equilibre'] ? 'Bilan équilibré' : 'Déséquilibre détecté' }}
    </span>
  </div>
</div>

<div class="columns">
  <div class="col actif">
    <div class="panel">
      <div class="panel-head">ACTIF <span class="amt">{{ number_format($data['totaux']['total_actif'], 0, ',', ' ') }} {{ $data['currency'] }}</span></div>
      @foreach($data['actif'] as $section)
        <div class="section-head">{{ $section['label_fr'] }} <span class="amt">{{ number_format($section['total'], 0, ',', ' ') }}</span></div>
        @if(count($section['accounts']))
          <table>
            <tbody>
              @foreach($section['accounts'] as $acc)
                <tr>
                  <td><span class="code">{{ $acc['account_code'] }}</span></td>
                  <td class="num">{{ number_format($acc['montant'], 0, ',', ' ') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <div class="empty">Aucun mouvement</div>
        @endif
      @endforeach
    </div>
  </div>

  <div class="col passif">
    <div class="panel">
      <div class="panel-head">PASSIF <span class="amt">{{ number_format($data['totaux']['total_passif'], 0, ',', ' ') }} {{ $data['currency'] }}</span></div>
      @foreach($data['passif'] as $section)
        <div class="section-head">{{ $section['label_fr'] }} <span class="amt">{{ number_format($section['total'], 0, ',', ' ') }}</span></div>
        @if(count($section['accounts']))
          <table>
            <tbody>
              @foreach($section['accounts'] as $acc)
                <tr>
                  <td><span class="code">{{ $acc['account_code'] }}</span>@if(!empty($acc['label_fr'])) — {{ $acc['label_fr'] }}@endif</td>
                  <td class="num">{{ number_format($acc['montant'], 0, ',', ' ') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <div class="empty">Aucun mouvement</div>
        @endif
      @endforeach
    </div>
  </div>
</div>

<div class="totals">
  <span>TOTAL ACTIF : {{ number_format($data['totaux']['total_actif'], 0, ',', ' ') }} {{ $data['currency'] }}</span>
  <span>TOTAL PASSIF : {{ number_format($data['totaux']['total_passif'], 0, ',', ' ') }} {{ $data['currency'] }}</span>
</div>

<div class="footer">
  Généré par {{ config('app.name') }} &bull; {{ now()->format('d/m/Y H:i') }} &bull; Devise : {{ $data['currency'] }}
</div>

</body>
</html>
