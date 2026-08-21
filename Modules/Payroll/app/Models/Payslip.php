<?php
declare(strict_types=1);
namespace Modules\Payroll\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;

class Payslip extends Model
{
    use AuditableActions, HasFactory;
    protected $table = 'payslips';
    protected $fillable = ['payroll_run_id','tenant_id','employee_id','employee_name','period','salary_components','gross_salary','total_deductions','net_salary','currency','status','paid_at'];
    protected $casts = ['period'=>'date','salary_components'=>'array','gross_salary'=>'decimal:2','total_deductions'=>'decimal:2','net_salary'=>'decimal:2','paid_at'=>'datetime'];

    /**
     * Chantier 32.18 (Payroll deep audit — layer 10, relational): neither
     * relation existed at all despite payroll_run_id being a real
     * constrained FK (payslips.payroll_run_id -> payroll_runs.id,
     * cascadeOnDelete) and employee_id being a real, always-populated
     * hr_employees.id — every real caller reached both concepts via a
     * separate manual query instead (PayrollController::taxesByCountry()'s
     * own Employee::whereIn(), PayrollRun::payslips() as the only side of
     * the pair that existed). employee_id is deliberately left unconstrained
     * at the DB level (no foreign key) — same cross-module pattern already
     * established elsewhere in this app (e.g. Inventory's CostingSheet.
     * opportunity_id) since Payroll declares HR as a soft dependency, not a
     * hard schema one.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
