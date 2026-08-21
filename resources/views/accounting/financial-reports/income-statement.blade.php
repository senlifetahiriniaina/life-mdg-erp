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
  .cards { display: flex; justify-content: space-between; margin-bottom: 20px; }
  .card { width: 19%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; }
  .card-label { font-size: 8px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
  .card-value { font-size: 12px; font-weight: bold; }
  .card-pos .card-value { color: #166534; }
  .card-neg .card-value { color: #dc2626; }
  .columns { display: flex; }
  .col { width: 50%; }
  .col + .col { padding-left: 12px; }
  .panel { border: 1px solid #e5e7eb; border-radius: 6px; }
  .panel-head { padding: 8px 12px; font-weight: bold; background: #f8faff; border-bottom: 1px solid #e5e7eb; }
  .panel-head .amt { float: right; }
  .produits .panel-head { color: #166534; }
  .charges .panel-head { color: #dc2626; }
  table { width: 100%; border-collapse: collapse; }
  thead th { background: #f3f4f6; padding: 5px 10px; text-align: left; font-size: 9px; }
  thead th.num { text-align: right; }
  tbody td { padding: 4px 10px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
  tbody td.num { text-align: right; font-variant-numeric: tabular-nums; }
  tfoot td { padding: 6px 10px; font-weight: bold; background: #f8faff; }
  tfoot td.num { text-align: right; }
  .code { font-family: monospace; color: #6b7280; font-size: 9px; }
  .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="company">{{ config('app.name') }}</div>
    <div class="subtitle">Compte de résultat SYSCOHADA — Chiffre d'affaires → marge → résultat d'exploitation → financier → net</div>
  </div>
  <div class="meta">
    <div class="title">COMPTE DE RÉSULTAT</div>
    <div>Période : {{ $data['period'] }}</div>
    <div>Généré le {{ now()->format('d/m/Y H:i') }}</div>
  </div>
</div>

<div class="cards">
  <div class="card">
    <div class="card-label">Chiffre d'affaires</div>
    <div class="card-value">{{ number_format($data['totaux']['chiffre_affaires'], 0, ',', ' ') }} {{ $data['currency'] }}</div>
  </div>
  <div class="card">
    <div class="card-label">Marge brute</div>
    <div class="card-value">{{ number_format($data['totaux']['marge_brute'], 0, ',', ' ') }} {{ $data['currency'] }}</div>
  </div>
  <div class="card {{ $data['totaux']['resultat_exploitation'] >= 0 ? 'card-pos' : 'card-neg' }}">
    <div class="card-label">Résultat d'exploitation</div>
    <div class="card-value">{{ number_format($data['totaux']['resultat_exploitation'], 0, ',', ' ') }} {{ $data['currency'] }}</div>
  </div>
  <div class="card {{ $data['totaux']['resultat_financier'] >= 0 ? 'card-pos' : 'card-neg' }}">
    <div class="card-label">Résultat financier</div>
    <div class="card-value">{{ number_format($data['totaux']['resultat_financier'], 0, ',', ' ') }} {{ $data['currency'] }}</div>
  </div>
  <div class="card {{ $data['totaux']['resultat_net'] >= 0 ? 'card-pos' : 'card-neg' }}">
    <div class="card-label">Résultat net</div>
    <div class="card-value">{{ number_format($data['totaux']['resultat_net'], 0, ',', ' ') }} {{ $data['currency'] }}</div>
  </div>
</div>

<div class="columns">
  <div class="col produits">
    <div class="panel">
      <div class="panel-head">PRODUITS (Classe 7) <span class="amt">{{ number_format($data['totaux']['total_produits'], 0, ',', ' ') }}</span></div>
      <table>
        <thead><tr><th>Compte</th><th class="num">Période</th><th class="num">N-1</th></tr></thead>
        <tbody>
          @foreach($data['produits'] as $row)
            <tr>
              <td><span class="code">{{ $row['code'] }}</span> {{ $row['label_fr'] }}</td>
              <td class="num">{{ number_format($row['current'], 0, ',', ' ') }}</td>
              <td class="num">{{ number_format($row['previous'], 0, ',', ' ') }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <td>Total produits</td>
            <td class="num">{{ number_format($data['totaux']['total_produits'], 0, ',', ' ') }}</td>
            <td class="num">{{ number_format($data['totaux']['total_produits_n1'], 0, ',', ' ') }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="col charges">
    <div class="panel">
      <div class="panel-head">CHARGES (Classe 6) <span class="amt">{{ number_format($data['totaux']['total_charges'], 0, ',', ' ') }}</span></div>
      <table>
        <thead><tr><th>Compte</th><th class="num">Période</th><th class="num">N-1</th></tr></thead>
        <tbody>
          @foreach($data['charges'] as $row)
            <tr>
              <td><span class="code">{{ $row['code'] }}</span> {{ $row['label_fr'] }}</td>
              <td class="num">{{ number_format($row['current'], 0, ',', ' ') }}</td>
              <td class="num">{{ number_format($row['previous'], 0, ',', ' ') }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <td>Total charges</td>
            <td class="num">{{ number_format($data['totaux']['total_charges'], 0, ',', ' ') }}</td>
            <td class="num">{{ number_format($data['totaux']['total_charges_n1'], 0, ',', ' ') }}</td>
          </tr>
          <tr>
            <td>Résultat net</td>
            <td class="num">{{ number_format($data['totaux']['resultat_net'], 0, ',', ' ') }}</td>
            <td class="num">{{ number_format($data['totaux']['resultat_net_n1'], 0, ',', ' ') }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<div class="footer">
  Généré par {{ config('app.name') }} &bull; {{ now()->format('d/m/Y H:i') }} &bull; Devise : {{ $data['currency'] }}
</div>

</body>
</html>
