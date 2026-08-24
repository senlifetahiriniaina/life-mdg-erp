<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a2e; }
  .header { display: flex; justify-content: space-between; margin-bottom: 32px; }
  .company { font-size: 22px; font-weight: bold; color: #2e5be8; }
  .meta { text-align: right; font-size: 11px; color: #555; }
  .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
  .badge-paid { background: #d1fae5; color: #065f46; }
  .badge-approved { background: #dbeafe; color: #1e40af; }
  .badge-draft { background: #f3f4f6; color: #374151; }
  .parties { display: flex; justify-content: space-between; margin-bottom: 24px; }
  .party { width: 48%; }
  .party-label { font-size: 10px; font-weight: bold; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
  .party-name { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  thead th { background: #2e5be8; color: #fff; padding: 8px 12px; text-align: left; font-size: 11px; }
  thead th.num { text-align: right; }
  tbody tr:nth-child(even) { background: #f8faff; }
  tbody td { padding: 6px 12px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
  tbody td.num { text-align: right; }
  .section-title { font-size: 11px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin: 16px 0 6px; }
  .totals { float: right; width: 260px; }
  .totals table { margin: 0; }
  .totals td { padding: 4px 8px; font-size: 11px; }
  .totals td.label { color: #6b7280; }
  .totals td.amount { text-align: right; font-weight: 500; }
  .totals tr.grand td { font-weight: bold; font-size: 14px; border-top: 2px solid #2e5be8; padding-top: 8px; }
  .footer { clear: both; margin-top: 48px; text-align: center; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 12px; }
</style>
</head>
<body>

@php
    $components = $payslip->salary_components ?? [];
    $allowances = $components['allowances'] ?? [];
    $overtime = $components['overtime'] ?? [];
    $bonuses = $components['bonuses'] ?? [];
    $deductionsBlock = $components['deductions']['deductions'] ?? [];
    $labels = [
        'housing_allowance' => 'Indemnité de logement',
        'transport_allowance' => 'Indemnité de transport',
        'family_allowance' => 'Allocations familiales',
        'performance_allowance' => 'Prime de performance',
        'monthly_bonus' => 'Prime mensuelle',
        'performance_bonus' => 'Prime de performance (bonus)',
        'income_tax' => 'Impôt sur le revenu (IRSA)',
        'social_security' => 'Sécurité sociale (CNaPS)',
        'health_insurance' => 'Assurance santé (OSTIE)',
        'pension_contribution' => 'Cotisation retraite',
        'loan_repayment' => 'Remboursement de prêt',
        'union_dues' => 'Cotisation syndicale',
        'unpaid_leave_deduction' => 'Retenue congé non payé',
    ];
    $money = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' ' . $payslip->currency;
@endphp

<div class="header">
  <div>
    <div class="company">{{ config('app.name') }}</div>
    <div style="font-size:11px;color:#6b7280;margin-top:4px">{{ config('app.url') }}</div>
  </div>
  <div class="meta">
    <div style="font-size:18px;font-weight:bold;margin-bottom:4px">BULLETIN DE PAIE</div>
    <div>#{{ $payslip->id }}</div>
    <div style="margin-top:6px">
      <span class="badge badge-{{ $payslip->status }}">{{ $payslip->status }}</span>
    </div>
  </div>
</div>

<div class="parties">
  <div class="party">
    <div class="party-label">Employé</div>
    <div class="party-name">{{ $payslip->employee_name }}</div>
    @if ($payslip->employee)
      <div style="color:#6b7280;font-size:11px">{{ $payslip->employee->employee_number }}</div>
    @endif
  </div>
  <div class="party" style="text-align:right">
    <div class="party-label">Période</div>
    {{-- Every other label on this document is hardcoded French, so the
         period is forced to the fr locale explicitly (Carbon::locale())
         rather than depending on config('app.locale'), which defaults to
         'en' in this app and would otherwise render "August 2026" next to
         "PÉRIODE"/"BULLETIN DE PAIE" — confirmed empirically via a real
         rendered PDF before this fix. --}}
    <div class="party-name">{{ $payslip->period?->locale('fr')->translatedFormat('F Y') ?? $payslip->period }}</div>
    @if ($payslip->paid_at)
      <div style="color:#6b7280;font-size:11px">Payé le {{ $payslip->paid_at->format('d/m/Y') }}</div>
    @endif
  </div>
</div>

<div class="section-title">Gains</div>
<table>
  <thead>
    <tr><th>Élément</th><th class="num">Montant</th></tr>
  </thead>
  <tbody>
    <tr><td>Salaire de base</td><td class="num">{{ $money($components['base_salary'] ?? $payslip->gross_salary) }}</td></tr>
    @foreach ($allowances as $key => $amount)
      @if ((float) $amount > 0)
        <tr><td>{{ $labels[$key] ?? $key }}</td><td class="num">{{ $money($amount) }}</td></tr>
      @endif
    @endforeach
    @if (($overtime['hours'] ?? 0) > 0)
      <tr><td>Heures supplémentaires ({{ number_format((float) $overtime['hours'], 1) }} h × {{ $overtime['multiplier'] ?? 1.5 }})</td><td class="num">{{ $money($overtime['total'] ?? 0) }}</td></tr>
    @endif
    @foreach ($bonuses as $key => $amount)
      @if ((float) $amount > 0)
        <tr><td>{{ $labels[$key] ?? $key }}</td><td class="num">{{ $money($amount) }}</td></tr>
      @endif
    @endforeach
  </tbody>
</table>

<div class="section-title">Retenues</div>
<table>
  <thead>
    <tr><th>Élément</th><th class="num">Montant</th></tr>
  </thead>
  <tbody>
    @forelse ($deductionsBlock as $key => $amount)
      @if ((float) $amount > 0)
        <tr><td>{{ $labels[$key] ?? $key }}</td><td class="num">{{ $money($amount) }}</td></tr>
      @endif
    @empty
      <tr><td colspan="2" style="color:#9ca3af">Aucune retenue</td></tr>
    @endforelse
  </tbody>
</table>

<div class="totals">
  <table>
    <tr><td class="label">Salaire brut</td><td class="amount">{{ $money($payslip->gross_salary) }}</td></tr>
    <tr><td class="label">Total des retenues</td><td class="amount">-{{ $money($payslip->total_deductions) }}</td></tr>
    <tr class="grand"><td class="label">Salaire net</td><td class="amount">{{ $money($payslip->net_salary) }}</td></tr>
  </table>
</div>

<div class="footer">
  Document généré automatiquement par {{ config('app.name') }} — {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
