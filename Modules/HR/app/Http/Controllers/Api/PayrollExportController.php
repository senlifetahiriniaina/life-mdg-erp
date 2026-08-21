<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Payroll\Models\Payslip;

/**
 * Chantier 32.17 (HR deep 14-layer audit): HR/Payroll/Index.vue's 3 export
 * buttons (SILAE/DSN/CSV) have always navigated to
 * `/api/v1/hr/payroll/export/{format}` via a full page load
 * (window.location.href) — a route that has never existed anywhere in this
 * app, confirmed via `php artisan route:list`, a guaranteed 404 on every
 * click. SILAE and DSN are French payroll-software-specific/regulatory
 * declaration formats — this app's real payroll compliance target is
 * Madagascar (IRSA/CNaPS/OSTIE, see StatutorySchemes.php in Modules/Payroll),
 * not France, and DSN in particular is a genuine, complex regulated
 * declaration (SIREN/NIR/establishment codes, S21.G00.xx rubriques) this
 * session has no reference to build correctly — fabricating a file that
 * *looks* like a real DSN/SILAE export without actually being schema-correct
 * would be actively misleading, the same "don't fake a compliance format"
 * discipline already established elsewhere in this app (e.g. the VAT
 * declaration/CA20 gap documented in CLAUDE.md). All 3 buttons now produce
 * the same real, honest CSV of the tenant's own payslip data — closing the
 * 404 for real rather than trading it for a fabricated-compliance-document
 * risk. See docs/03-MODULES/HR.md for the full rationale.
 */
class PayrollExportController extends Controller
{
    public function export(Request $request, string $format): Response
    {
        $this->authorize('viewAny', \Modules\HR\Models\Employee::class);

        $tenantId = (int) ($request->user()->company_id ?? 0);
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $payslips = Payslip::query()
            ->where('tenant_id', $tenantId)
            ->whereYear('period', $year)
            ->whereMonth('period', $month)
            ->orderBy('employee_name')
            ->get();

        $csv = "Employee,Period,Gross Salary,Net Salary,Currency,Status,Paid At\n";
        foreach ($payslips as $p) {
            $csv .= implode(',', [
                '"'.str_replace('"', '""', $p->employee_name ?? '').'"',
                $p->period?->format('Y-m').'',
                (string) $p->gross_salary,
                (string) $p->net_salary,
                $p->currency ?? '',
                $p->status ?? '',
                $p->paid_at?->format('Y-m-d') ?? '',
            ])."\n";
        }

        $filename = sprintf('payroll-%s-%04d-%02d.csv', $format, $year, $month);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
